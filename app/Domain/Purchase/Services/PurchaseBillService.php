<?php

namespace App\Domain\Purchase\Services;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Party\Models\Supplier;
use App\Domain\Purchase\Exceptions\PurchasePostingException;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use App\Domain\Purchase\Models\SupplierPaymentAllocation;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseBillService
{
    public function __construct(
        protected JournalPostingService $postingService,
        protected StockService $stockService
    ) {}

    /**
     * Post a draft bill → posts the PUR journal (Inventory/Expense Dr | Input Tax Dr |
     * AP Cr) and assigns the bill_no.
     *
     * @throws PurchasePostingException
     */
    public function postBill(PurchaseBill $bill): PurchaseBill
    {
        if (! $bill->isDraft()) {
            throw new PurchasePostingException('Only draft bills can be posted.');
        }

        return DB::transaction(function () use ($bill) {
            $bill->load(['lines.product', 'supplier']);

            if ($bill->lines->where('quantity', '>', 0)->count() === 0) {
                throw new PurchasePostingException('The bill needs at least one line with a positive quantity.');
            }

            $lines = $this->buildJournalLines($bill);

            $this->postingService->assertBalanced($lines);

            if (count($lines) < 2) {
                throw new PurchasePostingException('The bill must produce at least two journal lines.');
            }

            $billNo = $this->nextBillNumber($bill->company_id, $bill->bill_date);

            $journal = Journal::query()->create([
                'company_id' => $bill->company_id,
                'branch_id' => $bill->branch_id,
                'period_id' => $this->periodFor($bill->company_id, $bill->bill_date)?->id,
                'journal_date' => $bill->bill_date,
                'source_type' => JournalSourceType::PurchaseBill->value,
                'source_id' => $bill->id,
                'reference' => $billNo,
                'description' => 'Purchase bill '.$billNo,
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

            $bill->update([
                'bill_no' => $billNo,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->stockService->receivePurchaseBill($bill);

            return $bill->fresh()->load('lines');
        });
    }

    /**
     * Record a payment to a supplier against a bill → PMT journal
     * (AP Dr | Cash/Bank Cr) and bump the bill's paid amount.
     *
     * @param  array{payment_date: string, account_id: int, amount: float|string, reference?: ?string, memo?: ?string}  $data
     *
     * @throws PurchasePostingException
     */
    public function recordPayment(PurchaseBill $bill, array $data): SupplierPayment
    {
        if (! $bill->isPosted()) {
            throw new PurchasePostingException('Payments can only be recorded against posted bills.');
        }

        $amount = round((float) $data['amount'], 4);

        if ($amount <= 0) {
            throw new PurchasePostingException('The payment amount must be greater than zero.');
        }

        $balance = $bill->balanceDue();

        if ($amount > $balance + 0.0001) {
            throw new PurchasePostingException(
                'The payment exceeds the outstanding balance of '.number_format($balance, 2).' on this bill.'
            );
        }

        $bill->load('supplier');

        return DB::transaction(function () use ($bill, $data, $amount) {
            $account = Account::query()->find((int) $data['account_id']);

            if (! $account || ! $account->is_postable) {
                throw new PurchasePostingException('The payment account cannot receive postings.');
            }

            $paymentNo = $this->nextPaymentNumber($bill->company_id, $data['payment_date']);

            $payment = SupplierPayment::query()->create([
                'company_id' => $bill->company_id,
                'branch_id' => $bill->branch_id,
                'supplier_id' => $bill->supplier_id,
                'payment_no' => $paymentNo,
                'payment_date' => $data['payment_date'],
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

            SupplierPaymentAllocation::query()->create([
                'supplier_payment_id' => $payment->id,
                'purchase_bill_id' => $bill->id,
                'amount' => $amount,
            ]);

            $apAccount = $this->apAccountFor($bill->supplier);

            $journal = Journal::query()->create([
                'company_id' => $bill->company_id,
                'branch_id' => $bill->branch_id,
                'period_id' => $this->periodFor($bill->company_id, $data['payment_date'])?->id,
                'journal_date' => $data['payment_date'],
                'source_type' => JournalSourceType::Payment->value,
                'source_id' => $payment->id,
                'reference' => $paymentNo,
                'description' => 'Supplier payment '.$paymentNo,
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany([
                [
                    'account_id' => $apAccount,
                    'party_type' => 'supplier',
                    'party_id' => $bill->supplier_id,
                    'description' => 'Payment to '.$bill->supplier->name,
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $account->id,
                    'party_type' => null,
                    'party_id' => null,
                    'description' => 'Payment '.$paymentNo,
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ]);

            $this->postingService->post($journal);

            $bill->increment('amount_paid', $amount);

            return $payment;
        });
    }

    /**
     * Build the journal lines for a bill, recomputing every amount from the
     * persisted product/quantity/rate so posting never trusts client-side math.
     *
     * @return list<array{account_id: int, party_type: ?string, party_id: ?int, description: string, debit: float, credit: float}>
     */
    public function buildJournalLines(PurchaseBill $bill): array
    {
        $supplier = $bill->supplier;
        $apAccount = $this->apAccountFor($supplier);
        $setting = AccountingSetting::query()->where('company_id', $bill->company_id)->first();

        $apTotal = 0.0;
        $costByAccount = [];
        // [input_tax_account_id => amount]
        $taxByAccount = [];

        foreach ($bill->lines as $line) {
            $product = $line->product;

            if (! $product) {
                throw new PurchasePostingException('A line on this bill references a product that no longer exists.');
            }

            $quantity = (float) $line->quantity;
            $gross = round($quantity * (float) $line->unit_cost, 4);
            $discount = round((float) $line->discount_amount, 4);
            $lineTotal = round(max($gross - $discount, 0), 4);
            $taxPercent = (float) $line->tax_rate_percent;
            $taxAmount = round($lineTotal * $taxPercent / 100, 4);

            if ($lineTotal <= 0) {
                throw new PurchasePostingException("Line '{$product->name}' has a zero value after discount.");
            }

            $apTotal += $lineTotal + $taxAmount;

            $purchaseAccount = $product->purchase_account_id ?? $setting?->default_purchase_account_id;
            if (! $purchaseAccount || ! $this->isPostable($purchaseAccount)) {
                throw new PurchasePostingException("No postable purchase account is configured for '{$product->name}'.");
            }
            $costByAccount[(int) $purchaseAccount] = round(($costByAccount[(int) $purchaseAccount] ?? 0) + $lineTotal, 4);

            if ($taxAmount > 0) {
                $taxAccount = $line->tax_rate_id
                    ? optional($line->taxRate)->input_account_id
                    : $setting?->default_tax_input_account_id;
                $taxAccount = $taxAccount ?? $setting?->default_tax_input_account_id;

                if (! $taxAccount || ! $this->isPostable($taxAccount)) {
                    throw new PurchasePostingException('No postable input tax account is configured for the selected tax rate.');
                }
                $taxByAccount[(int) $taxAccount] = round(($taxByAccount[(int) $taxAccount] ?? 0) + $taxAmount, 4);
            }
        }

        $lines = [];

        foreach ($costByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'party_type' => null,
                'party_id' => null,
                'description' => 'Purchase cost',
                'debit' => $amount,
                'credit' => 0,
            ];
        }

        foreach ($taxByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'party_type' => null,
                'party_id' => null,
                'description' => 'Input tax paid',
                'debit' => $amount,
                'credit' => 0,
            ];
        }

        if ($apTotal > 0) {
            $lines[] = [
                'account_id' => $apAccount,
                'party_type' => 'supplier',
                'party_id' => $supplier->id,
                'description' => 'Purchase bill from '.$supplier->name,
                'debit' => 0,
                'credit' => round($apTotal, 4),
            ];
        }

        return $lines;
    }

    private function apAccountFor(Supplier $supplier): int
    {
        $accountId = $supplier->ap_account_id
            ?? AccountingSetting::query()->where('company_id', $supplier->company_id)->value('default_ap_account_id');

        if (! $accountId || ! $this->isPostable($accountId)) {
            throw new PurchasePostingException("No postable accounts payable account is configured for supplier '{$supplier->name}'.");
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

    public function nextBillNumber(int $companyId, string $date): string
    {
        $year = Carbon::parse($date)->format('Y');

        $latest = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('bill_no', 'like', "PB-{$year}-%")
            ->orderByDesc('bill_no')
            ->value('bill_no');

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("PB-{$year}-")->before('-')->toString()) + 1;
        }

        return sprintf('PB-%s-%04d', $year, $sequence);
    }

    public function nextPaymentNumber(int $companyId, string $date): string
    {
        $year = Carbon::parse($date)->format('Y');

        $latest = SupplierPayment::query()
            ->where('company_id', $companyId)
            ->where('payment_no', 'like', "PY-{$year}-%")
            ->orderByDesc('payment_no')
            ->value('payment_no');

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("PY-{$year}-")->before('-')->toString()) + 1;
        }

        return sprintf('PY-%s-%04d', $year, $sequence);
    }
}