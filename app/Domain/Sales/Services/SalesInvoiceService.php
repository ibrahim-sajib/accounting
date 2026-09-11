<?php

namespace App\Domain\Sales\Services;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Party\Models\Customer;
use App\Domain\Sales\Exceptions\SalesPostingException;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptAllocation;
use App\Domain\Sales\Models\SalesInvoice;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    public function __construct(
        protected JournalPostingService $postingService,
        protected StockService $stockService
    ) {}

    /**
     * Post a draft invoice → posts the SINV journal (AR Dr | Sales Cr | Tax Cr,
     * plus COGS Dr | Inventory Cr for product lines) and assigns the invoice_no.
     *
     * @throws SalesPostingException
     */
    public function postInvoice(SalesInvoice $invoice): SalesInvoice
    {
        if (! $invoice->isDraft()) {
            throw new SalesPostingException('Only draft invoices can be posted.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->load(['lines.product', 'customer']);

            if ($invoice->lines->where('quantity', '>', 0)->count() === 0) {
                throw new SalesPostingException('The invoice needs at least one line with a positive quantity.');
            }

            $lines = $this->buildJournalLines($invoice);

            $this->postingService->assertBalanced($lines);

            if (count($lines) < 2) {
                throw new SalesPostingException('The invoice must produce at least two journal lines.');
            }

            $invoiceNo = $this->nextInvoiceNumber($invoice->company_id, $invoice->invoice_date);

            $journal = Journal::query()->create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'period_id' => $this->periodFor($invoice->company_id, $invoice->invoice_date)?->id,
                'journal_date' => $invoice->invoice_date,
                'source_type' => JournalSourceType::SalesInvoice->value,
                'source_id' => $invoice->id,
                'reference' => $invoiceNo,
                'description' => 'Sales invoice '.$invoiceNo,
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany(collect($lines)->map(fn (array $l) => [
                'account_id' => $l['account_id'],
                'party_type' => $l['party_type'],
                'party_id' => $l['party_id'],
                'description' => $l['description'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ])->all());

            $this->postingService->post($journal);

            $invoice->update([
                'invoice_no' => $invoiceNo,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->stockService->issueSalesInvoice($invoice);

            return $invoice->fresh()->load('lines');
        });
    }

    /**
     * Post a cash/bank receipt against an invoice → RCT journal
     * (Cash/Bank Dr | AR Cr) and bump the invoice's paid amount.
     *
     * @param  array{receipt_date: string, account_id: int, amount: float|string, reference?: ?string, memo?: ?string}  $data
     *
     * @throws SalesPostingException
     */
    public function recordPayment(SalesInvoice $invoice, array $data): Receipt
    {
        if (! $invoice->isPosted()) {
            throw new SalesPostingException('Payments can only be recorded against posted invoices.');
        }

        $amount = round((float) $data['amount'], 4);

        if ($amount <= 0) {
            throw new SalesPostingException('The payment amount must be greater than zero.');
        }

        $balance = $invoice->balanceDue();

        if ($amount > $balance + 0.0001) {
            throw new SalesPostingException(
                'The payment exceeds the outstanding balance of '.number_format($balance, 2).' on this invoice.'
            );
        }

        $invoice->load('customer');

        return DB::transaction(function () use ($invoice, $data, $amount) {
            $account = Account::query()->find((int) $data['account_id']);

            if (! $account || ! $account->is_postable) {
                throw new SalesPostingException('The payment account cannot receive postings.');
            }

            $receiptNo = $this->nextReceiptNumber($invoice->company_id, $data['receipt_date']);

            $receipt = Receipt::query()->create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'customer_id' => $invoice->customer_id,
                'receipt_no' => $receiptNo,
                'receipt_date' => $data['receipt_date'],
                'account_id' => $account->id,
                'reference' => $data['reference'] ?? null,
                'memo' => $data['memo'] ?? null,
                'amount' => $amount,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            ReceiptAllocation::query()->create([
                'receipt_id' => $receipt->id,
                'sales_invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);

            $arAccount = $this->arAccountFor($invoice->customer);

            $journal = Journal::query()->create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'period_id' => $this->periodFor($invoice->company_id, $data['receipt_date'])?->id,
                'journal_date' => $data['receipt_date'],
                'source_type' => JournalSourceType::Receipt->value,
                'source_id' => $receipt->id,
                'reference' => $receiptNo,
                'description' => 'Customer receipt '.$receiptNo,
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany([
                [
                    'account_id' => $account->id,
                    'party_type' => null,
                    'party_id' => null,
                    'description' => 'Payment received from '.$invoice->customer->name,
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $arAccount,
                    'party_type' => 'customer',
                    'party_id' => $invoice->customer_id,
                    'description' => 'Receipt '.$receiptNo,
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ]);

            $this->postingService->post($journal);

            $invoice->increment('amount_paid', $amount);

            return $receipt;
        });
    }

    /**
     * Build the journal lines for an invoice, recomputing every amount from the
     * persisted product/quantity/rate so posting never trusts client-side math.
     *
     * @return list<array{account_id: int, party_type: ?string, party_id: ?int, description: string, debit: float, credit: float}>
     */
    public function buildJournalLines(SalesInvoice $invoice): array
    {
        $customer = $invoice->customer;
        $arAccount = $this->arAccountFor($customer);
        $setting = AccountingSetting::query()->where('company_id', $invoice->company_id)->first();

        $arTotal = 0.0;
        $revenueByAccount = [];
        $taxByAccount = [];
        // [cogs_account_id => amount], [inventory_account_id => amount]
        $cogsByAccount = [];
        $inventoryByAccount = [];

        foreach ($invoice->lines as $line) {
            $product = $line->product;

            if (! $product) {
                throw new SalesPostingException('A line on this invoice references a product that no longer exists.');
            }

            $quantity = (float) $line->quantity;
            $gross = round($quantity * (float) $line->unit_price, 4);
            $discount = round((float) $line->discount_amount, 4);
            $lineTotal = round(max($gross - $discount, 0), 4);
            $taxPercent = (float) $line->tax_rate_percent;
            $taxAmount = round($lineTotal * $taxPercent / 100, 4);

            if ($lineTotal <= 0) {
                throw new SalesPostingException("Line '{$product->name}' has a zero value after discount.");
            }

            $arTotal += $lineTotal + $taxAmount;

            $salesAccount = $product->sales_account_id ?? $setting?->default_sales_account_id;
            if (! $salesAccount || ! $this->isPostable($salesAccount)) {
                throw new SalesPostingException("No postable sales account is configured for '{$product->name}'.");
            }
            $revenueByAccount[(int) $salesAccount] = round(($revenueByAccount[(int) $salesAccount] ?? 0) + $lineTotal, 4);

            if ($taxAmount > 0) {
                $taxAccount = $line->tax_rate_id
                    ? optional($line->taxRate)->output_account_id
                    : $setting?->default_tax_output_account_id;
                $taxAccount = $taxAccount ?? $setting?->default_tax_output_account_id;

                if (! $taxAccount || ! $this->isPostable($taxAccount)) {
                    throw new SalesPostingException('No postable output tax account is configured for the selected tax rate.');
                }
                $taxByAccount[(int) $taxAccount] = round(($taxByAccount[(int) $taxAccount] ?? 0) + $taxAmount, 4);
            }

            if ($product->type === 'product') {
                $cost = round($quantity * $this->stockService->costFor($invoice->company_id, $line->product_id, $product), 4);

                $cogsAccount = $product->cogs_account_id
                    ?? Account::query()->where('company_id', $invoice->company_id)->where('code', '5221')->value('id')
                    ?? $setting?->default_purchase_account_id;
                $inventoryAccount = $product->inventory_account_id ?? $setting?->default_inventory_account_id;

                if (! $cogsAccount || ! $this->isPostable($cogsAccount)) {
                    throw new SalesPostingException("No postable COGS account is configured for '{$product->name}'.");
                }
                if (! $inventoryAccount || ! $this->isPostable($inventoryAccount)) {
                    throw new SalesPostingException("No postable inventory account is configured for '{$product->name}'.");
                }

                $cogsByAccount[(int) $cogsAccount] = round(($cogsByAccount[(int) $cogsAccount] ?? 0) + $cost, 4);
                $inventoryByAccount[(int) $inventoryAccount] = round(($inventoryByAccount[(int) $inventoryAccount] ?? 0) + $cost, 4);
            }
        }

        $lines = [];

        if ($arTotal > 0) {
            $lines[] = [
                'account_id' => $arAccount,
                'party_type' => 'customer',
                'party_id' => $customer->id,
                'description' => 'Sales invoice to '.$customer->name,
                'debit' => round($arTotal, 4),
                'credit' => 0,
            ];
        }

        foreach ($revenueByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'party_type' => null,
                'party_id' => null,
                'description' => 'Sales revenue',
                'debit' => 0,
                'credit' => $amount,
            ];
        }

        foreach ($taxByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'party_type' => null,
                'party_id' => null,
                'description' => 'Output tax collected',
                'debit' => 0,
                'credit' => $amount,
            ];
        }

        foreach ($cogsByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'party_type' => null,
                'party_id' => null,
                'description' => 'Cost of goods sold',
                'debit' => $amount,
                'credit' => 0,
            ];
        }

        foreach ($inventoryByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'party_type' => null,
                'party_id' => null,
                'description' => 'Inventory reduction on sale',
                'debit' => 0,
                'credit' => $amount,
            ];
        }

        return $lines;
    }

    private function arAccountFor(Customer $customer): int
    {
        $accountId = $customer->ar_account_id
            ?? AccountingSetting::query()->where('company_id', $customer->company_id)->value('default_ar_account_id');

        if (! $accountId || ! $this->isPostable($accountId)) {
            throw new SalesPostingException("No postable accounts receivable account is configured for customer '{$customer->name}'.");
        }

        return (int) $accountId;
    }

    private function isPostable(int $accountId): bool
    {
        $account = Account::query()->find($accountId);

        return $account && $account->is_postable;
    }

    private function periodFor(int $companyId, string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    public function nextInvoiceNumber(int $companyId, string $date): string
    {
        $year = Carbon::parse($date)->format('Y');

        $latest = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('invoice_no', 'like', "SL-{$year}-%")
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("SL-{$year}-")->before('-')->toString()) + 1;
        }

        return sprintf('SL-%s-%04d', $year, $sequence);
    }

    public function nextReceiptNumber(int $companyId, string $date): string
    {
        $year = Carbon::parse($date)->format('Y');

        $latest = Receipt::query()
            ->where('company_id', $companyId)
            ->where('receipt_no', 'like', "RC-{$year}-%")
            ->orderByDesc('receipt_no')
            ->value('receipt_no');

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("RC-{$year}-")->before('-')->toString()) + 1;
        }

        return sprintf('RC-%s-%04d', $year, $sequence);
    }
}