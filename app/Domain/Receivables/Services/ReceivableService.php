<?php

namespace App\Domain\Receivables\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Party\Models\Customer;
use App\Domain\Receivables\Exceptions\ReceivablePostingException;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptAllocation;
use App\Domain\Sales\Models\SalesInvoice;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\ReceiptType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accounts Receivable engine (Phase 8): multi-invoice receipts, advance receipts,
 * advance application, and invoice write-offs — each driven by a balanced journal.
 */
class ReceivableService
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    /**
     * Record a single-nature receipt against one or more posted invoices.
     * Cash/Bank Dr (total) | AR Cr (total) → RCT journal, each line credited
     * against the invoice's live balance (multi-alloc).
     *
     * @param  array{receipt_date: string, account_id: int, reference?: ?string, memo?: ?string, allocations: array<int, array{sales_invoice_id: int, amount: float|string}>}  $data
     */
    public function recordPayment(Customer $customer, array $data): Receipt
    {
        $allocations = collect($data['allocations'])->filter(fn ($a) => (float) $a['amount'] > 0)->values();

        if ($allocations->isEmpty()) {
            throw new ReceivablePostingException('At least one invoice must receive a payment amount.');
        }

        return DB::transaction(function () use ($customer, $data, $allocations) {
            $invoices = $this->resolveInvoices($customer, $allocations);

            $amount = round($allocations->sum(fn ($a) => (float) $a['amount']), 4);

            if ($amount <= 0) {
                throw new ReceivablePostingException('The payment amount must be greater than zero.');
            }

            $account = $this->paymentAccount((int) $data['account_id']);
            $arAccount = $this->arAccountFor($customer);

            $receiptNo = $this->nextReceiptNumber($customer->company_id, $data['receipt_date']);

            $receipt = $this->createReceipt($customer, [
                'type' => ReceiptType::Receipt->value,
                'receipt_no' => $receiptNo,
                'receipt_date' => $data['receipt_date'],
                'account_id' => $account->id,
                'reference' => $data['reference'] ?? null,
                'memo' => $data['memo'] ?? null,
                'amount' => $amount,
            ]);

            foreach ($invoices as $entry) {
                ReceiptAllocation::query()->create([
                    'receipt_id' => $receipt->id,
                    'sales_invoice_id' => $entry['invoice']->id,
                    'amount' => $entry['amount'],
                ]);

                $entry['invoice']->increment('amount_paid', $entry['amount']);
            }

            $this->postJournal($customer, $receipt, [
                'source_type' => JournalSourceType::Receipt->value,
                'reference' => $receiptNo,
                'description' => 'Customer receipt '.$receiptNo,
                'lines' => [
                    [
                        'account_id' => $account->id,
                        'party_type' => null,
                        'party_id' => null,
                        'description' => 'Payment received from '.$customer->name,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $arAccount,
                        'party_type' => 'customer',
                        'party_id' => $customer->id,
                        'description' => 'Receipt '.$receiptNo,
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                ],
            ]);

            return $receipt;
        });
    }

    /**
     * Record an advance receipt (no invoice). Cash/Bank Dr | Customer Advances Cr → RCT journal.
     *
     * @param  array{receipt_date: string, account_id: int, amount: float|string, reference?: ?string, memo?: ?string}  $data
     */
    public function recordAdvance(Customer $customer, array $data): Receipt
    {
        $amount = round((float) $data['amount'], 4);

        if ($amount <= 0) {
            throw new ReceivablePostingException('The advance amount must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $data, $amount) {
            $account = $this->paymentAccount((int) $data['account_id']);
            $advanceAccount = $this->advanceAccountFor($customer->company_id);

            $receiptNo = $this->nextReceiptNumber($customer->company_id, $data['receipt_date']);

            $receipt = $this->createReceipt($customer, [
                'type' => ReceiptType::Advance->value,
                'receipt_no' => $receiptNo,
                'receipt_date' => $data['receipt_date'],
                'account_id' => $account->id,
                'reference' => $data['reference'] ?? null,
                'memo' => $data['memo'] ?? null,
                'amount' => $amount,
            ]);

            $this->postJournal($customer, $receipt, [
                'source_type' => JournalSourceType::Receipt->value,
                'reference' => $receiptNo,
                'description' => 'Customer advance '.$receiptNo,
                'lines' => [
                    [
                        'account_id' => $account->id,
                        'party_type' => null,
                        'party_id' => null,
                        'description' => 'Advance received from '.$customer->name,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $advanceAccount,
                        'party_type' => 'customer',
                        'party_id' => $customer->id,
                        'description' => 'Customer advance '.$receiptNo,
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                ],
            ]);

            return $receipt;
        });
    }

    /**
     * Apply a posted advance to one or more posted invoices.
     * Customer Advances Dr | AR Cr (total) → RCA journal.
     *
     * @param  array<int, array{sales_invoice_id: int, amount: float|string}>  $allocations
     */
    public function applyAdvance(Receipt $advance, array $allocations): Receipt
    {
        if (! $advance->isAdvance()) {
            throw new ReceivablePostingException('Only an advance receipt can be applied to invoices.');
        }

        if ($advance->status !== TransactionStatus::Posted->value) {
            throw new ReceivablePostingException('Only a posted advance can be applied to invoices.');
        }

        $allocations = collect($allocations)->filter(fn ($a) => (float) $a['amount'] > 0)->values();

        if ($allocations->isEmpty()) {
            throw new ReceivablePostingException('At least one invoice must receive an application amount.');
        }

        return DB::transaction(function () use ($advance, $allocations) {
            $customer = $advance->customer;
            $invoices = $this->resolveInvoices($customer, $allocations);

            $amount = round($allocations->sum(fn ($a) => (float) $a['amount']), 4);
            $available = $advance->advanceBalance();

            if ($amount > $available + 0.0001) {
                throw new ReceivablePostingException(
                    'The application exceeds the unapplied advance of '.number_format($available, 2).' on this receipt.'
                );
            }

            $advanceAccount = $this->advanceAccountFor($advance->company_id);
            $arAccount = $this->arAccountFor($customer);

            foreach ($invoices as $entry) {
                ReceiptAllocation::query()->create([
                    'receipt_id' => $advance->id,
                    'sales_invoice_id' => $entry['invoice']->id,
                    'amount' => $entry['amount'],
                ]);

                $entry['invoice']->increment('amount_paid', $entry['amount']);
            }

            $this->postJournal($customer, $advance, [
                'source_type' => JournalSourceType::ReceiptApplication->value,
                'reference' => $advance->receipt_no,
                'description' => 'Advance '.$advance->receipt_no.' applied to invoices',
                'lines' => [
                    [
                        'account_id' => $advanceAccount,
                        'party_type' => 'customer',
                        'party_id' => $customer->id,
                        'description' => 'Advance '.$advance->receipt_no,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $arAccount,
                        'party_type' => 'customer',
                        'party_id' => $customer->id,
                        'description' => 'Advance applied to invoices',
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                ],
            ]);

            return $advance;
        });
    }

    /**
     * Write off part or all of a posted invoice's outstanding balance.
     * Bad Debt Expense Dr | AR Cr → WOF journal.
     *
     * @param  array{amount: float|string, reason: string}  $data
     */
    public function writeOff(SalesInvoice $invoice, array $data): SalesInvoice
    {
        if (! $invoice->isPosted()) {
            throw new ReceivablePostingException('Only posted invoices can be written off.');
        }

        $amount = round((float) $data['amount'], 4);
        $balance = $invoice->balanceDue();

        if ($amount <= 0) {
            throw new ReceivablePostingException('The write-off amount must be greater than zero.');
        }

        if ($amount > $balance + 0.0001) {
            throw new ReceivablePostingException(
                'The write-off exceeds the outstanding balance of '.number_format($balance, 2).' on this invoice.'
            );
        }

        return DB::transaction(function () use ($invoice, $data, $amount) {
            if (trim((string) ($data['reason'] ?? '')) === '') {
                throw new ReceivablePostingException('A write-off reason is required.');
            }

            $customer = $invoice->customer;
            $badDebtAccount = $this->badDebtAccountFor($invoice->company_id);
            $arAccount = $this->arAccountFor($customer);

            $invoice->update([
                'write_off_amount' => round((float) $invoice->write_off_amount + $amount, 4),
                'write_off_reason' => $data['reason'],
                'written_off_at' => now(),
                'written_off_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->postJournal($customer, $invoice, [
                'source_type' => JournalSourceType::WriteOff->value,
                'source_id' => $invoice->id,
                'reference' => $invoice->invoice_no,
                'description' => 'Write-off '.$invoice->invoice_no,
                'lines' => [
                    [
                        'account_id' => $badDebtAccount,
                        'party_type' => null,
                        'party_id' => null,
                        'description' => 'Bad debt write-off — '.$invoice->invoice_no,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $arAccount,
                        'party_type' => 'customer',
                        'party_id' => $customer->id,
                        'description' => 'Bad debt write-off — '.$invoice->invoice_no,
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                ],
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * @throws ReceivablePostingException
     */
    protected function resolveInvoices(Customer $customer, Collection $allocations): Collection
    {
        $ids = $allocations->pluck('sales_invoice_id')->map(fn ($id) => (int) $id)->all();

        if (count($ids) !== count(array_unique($ids))) {
            throw new ReceivablePostingException('The same invoice cannot be allocated twice in one receipt.');
        }

        $invoices = SalesInvoice::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $entries = [];

        foreach ($allocations as $allocation) {
            $id = (int) $allocation['sales_invoice_id'];
            $amount = round((float) $allocation['amount'], 4);

            $invoice = $invoices[$id] ?? null;

            if (! $invoice) {
                throw new ReceivablePostingException('An allocated invoice no longer exists.');
            }

            if ($invoice->company_id !== $customer->company_id || $invoice->customer_id !== $customer->id) {
                throw new ReceivablePostingException("Invoice {$invoice->invoice_no} does not belong to this customer.");
            }

            if (! $invoice->isPosted()) {
                throw new ReceivablePostingException("Invoice {$invoice->invoice_no} has not been posted yet.");
            }

            if ($amount <= 0) {
                throw new ReceivablePostingException("Invoice {$invoice->invoice_no} needs a positive allocation.");
            }

            $balance = $invoice->balanceDue();

            if ($amount > $balance + 0.0001) {
                throw new ReceivablePostingException(
                    "Allocation exceeds the outstanding balance of ".number_format($balance, 2)." on invoice {$invoice->invoice_no}."
                );
            }

            $entries[] = [
                'invoice' => $invoice,
                'amount' => $amount,
            ];
        }

        return collect($entries);
    }

    protected function createReceipt(Customer $customer, array $payload): Receipt
    {
        return Receipt::query()->create([
            'company_id' => $customer->company_id,
            'branch_id' => session('active_branch_id'),
            'customer_id' => $customer->id,
            'type' => $payload['type'],
            'receipt_no' => $payload['receipt_no'],
            'receipt_date' => $payload['receipt_date'],
            'account_id' => $payload['account_id'],
            'reference' => $payload['reference'],
            'memo' => $payload['memo'],
            'amount' => $payload['amount'],
            'status' => TransactionStatus::Posted->value,
            'posted_at' => now(),
            'posted_by' => Auth::id(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    protected function postJournal(Customer $customer, object $source, array $payload): Journal
    {
        $journal = Journal::query()->create([
            'company_id' => $customer->company_id,
            'branch_id' => $source->branch_id ?? session('active_branch_id'),
            'period_id' => $this->periodFor($customer->company_id, $source->receipt_date ?? $source->invoice_date)?->id,
            'journal_date' => $source->receipt_date ?? $source->invoice_date,
            'source_type' => $payload['source_type'],
            'source_id' => $source->id,
            'reference' => $payload['reference'],
            'description' => $payload['description'],
            'status' => TransactionStatus::Draft->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $journal->lines()->createMany($payload['lines']);

        $this->postingService->post($journal);

        return $journal;
    }

    protected function paymentAccount(int $accountId): Account
    {
        $account = Account::query()->find($accountId);

        if (! $account || ! $account->is_postable) {
            throw new ReceivablePostingException('The payment account cannot receive postings.');
        }

        return $account;
    }

    protected function arAccountFor(Customer $customer): int
    {
        $accountId = $customer->ar_account_id
            ?? AccountingSetting::query()->where('company_id', $customer->company_id)->value('default_ar_account_id');

        if (! $accountId || ! $this->isPostable((int) $accountId)) {
            throw new ReceivablePostingException("No postable accounts receivable account is configured for customer '{$customer->name}'.");
        }

        return (int) $accountId;
    }

    protected function advanceAccountFor(int $companyId): int
    {
        $accountId = Account::query()->where('company_id', $companyId)->where('code', '2161')->value('id');

        if (! $accountId || ! $this->isPostable((int) $accountId)) {
            throw new ReceivablePostingException('No postable "Customer Advances" account (2161) is configured.');
        }

        return (int) $accountId;
    }

    protected function badDebtAccountFor(int $companyId): int
    {
        $accountId = Account::query()->where('company_id', $companyId)->where('code', '5191')->value('id');

        if (! $accountId || ! $this->isPostable((int) $accountId)) {
            throw new ReceivablePostingException('No postable "Bad Debt Expense" account (5191) is configured.');
        }

        return (int) $accountId;
    }

    protected function isPostable(int $accountId): bool
    {
        $account = Account::query()->find($accountId);

        return $account && $account->is_postable;
    }

    protected function periodFor(int $companyId, string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
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

    /**
     * Aggregate AR report data for a company (dashboard, aging, outstanding).
     */
    public function receivableRows(int $companyId): Collection
    {
        return SalesInvoice::query()
            ->with('customer:id,name,code')
            ->where('company_id', $companyId)
            ->where('status', TransactionStatus::Posted->value)
            ->where('total', '>', 0)
            ->get()
            ->filter(fn (SalesInvoice $invoice) => $invoice->balanceDue() > 0)
            ->map(fn (SalesInvoice $invoice) => $this->rowShape($invoice))
            ->values();
    }

    public function rowShape(SalesInvoice $invoice): array
    {
        $dueDate = $invoice->due_date;
        $daysOverdue = $dueDate && $dueDate->lt(today()) ? $dueDate->diffInDays(today(), false) : 0;

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'customer_id' => $invoice->customer_id,
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'code' => $invoice->customer->code,
                'name' => $invoice->customer->name,
            ] : null,
            'total' => (float) $invoice->total,
            'amount_paid' => (float) $invoice->amount_paid,
            'write_off_amount' => (float) $invoice->write_off_amount,
            'balance_due' => round($invoice->balanceDue(), 4),
            'days_overdue' => (int) $daysOverdue,
            'paid_state' => $invoice->paidState(),
        ];
    }

    public function agingRows(int $companyId): array
    {
        $rows = $this->receivableRows($companyId);
        $customers = collect();

        foreach ($rows as $row) {
            $key = $row['customer_id'];

            if (! $customers->has($key)) {
                $customers[$key] = [
                    'customer_id' => $key,
                    'customer' => $row['customer'],
                    'current' => 0.0,
                    'b1_30' => 0.0,
                    'b31_60' => 0.0,
                    'b61_90' => 0.0,
                    'b90' => 0.0,
                    'total' => 0.0,
                    'invoices' => [],
                ];
            }

            $customer = $customers[$key];
            $customer[$this->bucketFor($row['days_overdue'])] = round($customer[$this->bucketFor($row['days_overdue'])] + $row['balance_due'], 4);
            $customer['total'] = round($customer['total'] + $row['balance_due'], 4);
            $customer['invoices'][] = $row;
            $customers[$key] = $customer;
        }

        return $customers->sortByDesc('total')->values()->all();
    }

    public function bucketFor(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return 'current';
        }

        if ($daysOverdue <= 30) {
            return 'b1_30';
        }

        if ($daysOverdue <= 60) {
            return 'b31_60';
        }

        if ($daysOverdue <= 90) {
            return 'b61_90';
        }

        return 'b90';
    }

    public function dashboard(int $companyId): array
    {
        $rows = $this->receivableRows($companyId);

        $totalReceivable = round($rows->sum('balance_due'), 4);
        $overdue = round($rows->where('days_overdue', '>', 0)->sum('balance_due'), 4);

        $startOfMonth = now()->startOfMonth()->toDateString();
        $collectedThisMonth = ReceiptAllocation::query()
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipts.company_id', $companyId)
            ->where('receipts.receipt_date', '>=', $startOfMonth)
            ->sum('receipt_allocations.amount');

        $advanceBalance = Receipt::query()
            ->where('company_id', $companyId)
            ->where('type', ReceiptType::Advance->value)
            ->get()
            ->sum(fn (Receipt $receipt) => $receipt->advanceBalance());

        $topCustomers = $rows
            ->groupBy('customer_id')
            ->map(fn ($group) => [
                'customer_id' => $group->first()['customer']['id'],
                'customer' => $group->first()['customer'],
                'balance_due' => round($group->sum('balance_due'), 4),
                'invoice_count' => $group->count(),
            ])
            ->sortByDesc('balance_due')
            ->take(5)
            ->values()
            ->all();

        $recentReceipts = Receipt::query()
            ->with('customer:id,name,code')
            ->where('company_id', $companyId)
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return [
            'total_receivable' => $totalReceivable,
            'overdue' => $overdue,
            'collected_this_month' => round((float) $collectedThisMonth, 4),
            'advance_balance' => round($advanceBalance, 4),
            'top_customers' => $topCustomers,
            'recent_receipts' => $recentReceipts->map(function (Receipt $receipt) {
                return [
                    'id' => $receipt->id,
                    'type' => $receipt->type,
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_date' => $receipt->receipt_date?->toDateString(),
                    'customer' => $receipt->customer ? [
                        'id' => $receipt->customer->id,
                        'code' => $receipt->customer->code,
                        'name' => $receipt->customer->name,
                    ] : null,
                    'amount' => (float) $receipt->amount,
                    'applied_amount' => $receipt->appliedAmount(),
                ];
            })->values()->all(),
        ];
    }
}