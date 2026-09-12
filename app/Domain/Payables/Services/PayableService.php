<?php

namespace App\Domain\Payables\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Party\Models\Supplier;
use App\Domain\Payables\Exceptions\PayablePostingException;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use App\Domain\Purchase\Models\SupplierPaymentAllocation;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\SupplierPaymentType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accounts Payable engine (Phase 9): multi-bill supplier payments and supplier
 * advances — each driven by a balanced journal.
 */
class PayableService
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    /**
     * Record a single-nature supplier payment against one or more posted bills.
     * AP Dr (total) | Cash/Bank Cr (total) → PMT journal, each line debited
     * against the bill's live balance (multi-alloc).
     *
     * @param  array{payment_date: string, account_id: int, reference?: ?string, memo?: ?string, allocations: array<int, array{purchase_bill_id: int, amount: float|string}>}  $data
     */
    public function recordPayment(Supplier $supplier, array $data): SupplierPayment
    {
        $allocations = collect($data['allocations'])->filter(fn ($a) => (float) $a['amount'] > 0)->values();

        if ($allocations->isEmpty()) {
            throw new PayablePostingException('At least one bill must receive a payment amount.');
        }

        return DB::transaction(function () use ($supplier, $data, $allocations) {
            $bills = $this->resolveBills($supplier, $allocations);

            $amount = round($allocations->sum(fn ($a) => (float) $a['amount']), 4);

            if ($amount <= 0) {
                throw new PayablePostingException('The payment amount must be greater than zero.');
            }

            $account = $this->paymentAccount((int) $data['account_id']);
            $apAccount = $this->apAccountFor($supplier);

            $paymentNo = $this->nextPaymentNumber($supplier->company_id, $data['payment_date']);

            $payment = $this->createPayment($supplier, [
                'type' => SupplierPaymentType::Payment->value,
                'payment_no' => $paymentNo,
                'payment_date' => $data['payment_date'],
                'account_id' => $account->id,
                'reference' => $data['reference'] ?? null,
                'memo' => $data['memo'] ?? null,
                'amount' => $amount,
            ]);

            foreach ($bills as $entry) {
                SupplierPaymentAllocation::query()->create([
                    'supplier_payment_id' => $payment->id,
                    'purchase_bill_id' => $entry['bill']->id,
                    'amount' => $entry['amount'],
                ]);

                $entry['bill']->increment('amount_paid', $entry['amount']);
            }

            $this->postJournal($supplier, $payment, [
                'source_type' => JournalSourceType::Payment->value,
                'reference' => $paymentNo,
                'description' => 'Supplier payment '.$paymentNo,
                'lines' => [
                    [
                        'account_id' => $apAccount,
                        'party_type' => 'supplier',
                        'party_id' => $supplier->id,
                        'description' => 'Payment to '.$supplier->name,
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
                ],
            ]);

            return $payment;
        });
    }

    /**
     * Record an advance payment to a supplier (no bill yet).
     * Supplier Advances (Asset) Dr | Cash/Bank Cr → PMT journal.
     *
     * @param  array{payment_date: string, account_id: int, amount: float|string, reference?: ?string, memo?: ?string}  $data
     */
    public function recordAdvance(Supplier $supplier, array $data): SupplierPayment
    {
        $amount = round((float) $data['amount'], 4);

        if ($amount <= 0) {
            throw new PayablePostingException('The advance amount must be greater than zero.');
        }

        return DB::transaction(function () use ($supplier, $data, $amount) {
            $account = $this->paymentAccount((int) $data['account_id']);
            $advanceAccount = $this->advanceAccountFor($supplier->company_id);

            $paymentNo = $this->nextPaymentNumber($supplier->company_id, $data['payment_date']);

            $payment = $this->createPayment($supplier, [
                'type' => SupplierPaymentType::Advance->value,
                'payment_no' => $paymentNo,
                'payment_date' => $data['payment_date'],
                'account_id' => $account->id,
                'reference' => $data['reference'] ?? null,
                'memo' => $data['memo'] ?? null,
                'amount' => $amount,
            ]);

            $this->postJournal($supplier, $payment, [
                'source_type' => JournalSourceType::Payment->value,
                'reference' => $paymentNo,
                'description' => 'Supplier advance '.$paymentNo,
                'lines' => [
                    [
                        'account_id' => $advanceAccount,
                        'party_type' => 'supplier',
                        'party_id' => $supplier->id,
                        'description' => 'Advance to '.$supplier->name,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $account->id,
                        'party_type' => null,
                        'party_id' => null,
                        'description' => 'Advance '.$paymentNo,
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                ],
            ]);

            return $payment;
        });
    }

    /**
     * Apply a posted advance to one or more posted bills.
     * AP Dr | Supplier Advances Cr (total) → SAA journal.
     *
     * @param  array<int, array{purchase_bill_id: int, amount: float|string}>  $allocations
     */
    public function applyAdvance(SupplierPayment $advance, array $allocations): SupplierPayment
    {
        if (! $advance->isAdvance()) {
            throw new PayablePostingException('Only an advance payment can be applied to bills.');
        }

        if ($advance->status !== TransactionStatus::Posted->value) {
            throw new PayablePostingException('Only a posted advance can be applied to bills.');
        }

        $allocations = collect($allocations)->filter(fn ($a) => (float) $a['amount'] > 0)->values();

        if ($allocations->isEmpty()) {
            throw new PayablePostingException('At least one bill must receive an application amount.');
        }

        return DB::transaction(function () use ($advance, $allocations) {
            $supplier = $advance->supplier;
            $bills = $this->resolveBills($supplier, $allocations);

            $amount = round($allocations->sum(fn ($a) => (float) $a['amount']), 4);
            $available = $advance->advanceBalance();

            if ($amount > $available + 0.0001) {
                throw new PayablePostingException(
                    'The application exceeds the unapplied advance of '.number_format($available, 2).' on this payment.'
                );
            }

            $advanceAccount = $this->advanceAccountFor($advance->company_id);
            $apAccount = $this->apAccountFor($supplier);

            foreach ($bills as $entry) {
                SupplierPaymentAllocation::query()->create([
                    'supplier_payment_id' => $advance->id,
                    'purchase_bill_id' => $entry['bill']->id,
                    'amount' => $entry['amount'],
                ]);

                $entry['bill']->increment('amount_paid', $entry['amount']);
            }

            $this->postJournal($supplier, $advance, [
                'source_type' => JournalSourceType::PaymentApplication->value,
                'reference' => $advance->payment_no,
                'description' => 'Advance '.$advance->payment_no.' applied to bills',
                'lines' => [
                    [
                        'account_id' => $apAccount,
                        'party_type' => 'supplier',
                        'party_id' => $supplier->id,
                        'description' => 'Advance '.$advance->payment_no,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $advanceAccount,
                        'party_type' => 'supplier',
                        'party_id' => $supplier->id,
                        'description' => 'Advance applied to bills',
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                ],
            ]);

            return $advance;
        });
    }

    /**
     * @throws PayablePostingException
     */
    protected function resolveBills(Supplier $supplier, Collection $allocations): Collection
    {
        $ids = $allocations->pluck('purchase_bill_id')->map(fn ($id) => (int) $id)->all();

        if (count($ids) !== count(array_unique($ids))) {
            throw new PayablePostingException('The same bill cannot be allocated twice in one payment.');
        }

        $bills = PurchaseBill::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $entries = [];

        foreach ($allocations as $allocation) {
            $id = (int) $allocation['purchase_bill_id'];
            $amount = round((float) $allocation['amount'], 4);

            $bill = $bills[$id] ?? null;

            if (! $bill) {
                throw new PayablePostingException('An allocated bill no longer exists.');
            }

            if ($bill->company_id !== $supplier->company_id || $bill->supplier_id !== $supplier->id) {
                throw new PayablePostingException("Bill {$bill->bill_no} does not belong to this supplier.");
            }

            if (! $bill->isPosted()) {
                throw new PayablePostingException("Bill {$bill->bill_no} has not been posted yet.");
            }

            if ($amount <= 0) {
                throw new PayablePostingException("Bill {$bill->bill_no} needs a positive allocation.");
            }

            $balance = $bill->balanceDue();

            if ($amount > $balance + 0.0001) {
                throw new PayablePostingException(
                    "Allocation exceeds the outstanding balance of ".number_format($balance, 2)." on bill {$bill->bill_no}."
                );
            }

            $entries[] = [
                'bill' => $bill,
                'amount' => $amount,
            ];
        }

        return collect($entries);
    }

    protected function createPayment(Supplier $supplier, array $payload): SupplierPayment
    {
        return SupplierPayment::query()->create([
            'company_id' => $supplier->company_id,
            'branch_id' => session('active_branch_id'),
            'supplier_id' => $supplier->id,
            'type' => $payload['type'],
            'payment_no' => $payload['payment_no'],
            'payment_date' => $payload['payment_date'],
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

    protected function postJournal(Supplier $supplier, object $source, array $payload): Journal
    {
        $journal = Journal::query()->create([
            'company_id' => $supplier->company_id,
            'branch_id' => $source->branch_id ?? session('active_branch_id'),
            'period_id' => $this->periodFor($supplier->company_id, $source->payment_date)?->id,
            'journal_date' => $source->payment_date,
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
            throw new PayablePostingException('The payment account cannot receive postings.');
        }

        return $account;
    }

    protected function apAccountFor(Supplier $supplier): int
    {
        $accountId = $supplier->ap_account_id
            ?? AccountingSetting::query()->where('company_id', $supplier->company_id)->value('default_ap_account_id');

        if (! $accountId || ! $this->isPostable((int) $accountId)) {
            throw new PayablePostingException("No postable accounts payable account is configured for supplier '{$supplier->name}'.");
        }

        return (int) $accountId;
    }

    protected function advanceAccountFor(int $companyId): int
    {
        $accountId = Account::query()->where('company_id', $companyId)->where('code', '1161')->value('id');

        if (! $accountId || ! $this->isPostable((int) $accountId)) {
            throw new PayablePostingException('No postable "Advances to Suppliers" account (1161) is configured.');
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

    /**
     * Aggregate AP report data for a company (dashboard, aging, outstanding).
     */
    public function payableRows(int $companyId): Collection
    {
        return PurchaseBill::query()
            ->with('supplier:id,name,code')
            ->where('company_id', $companyId)
            ->where('status', TransactionStatus::Posted->value)
            ->where('total', '>', 0)
            ->get()
            ->filter(fn (PurchaseBill $bill) => $bill->balanceDue() > 0)
            ->map(fn (PurchaseBill $bill) => $this->rowShape($bill))
            ->values();
    }

    public function rowShape(PurchaseBill $bill): array
    {
        $dueDate = $bill->due_date;
        $daysOverdue = $dueDate && $dueDate->lt(today()) ? $dueDate->diffInDays(today(), false) : 0;

        return [
            'id' => $bill->id,
            'bill_no' => $bill->bill_no,
            'bill_date' => $bill->bill_date?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'supplier_id' => $bill->supplier_id,
            'supplier' => $bill->supplier ? [
                'id' => $bill->supplier->id,
                'code' => $bill->supplier->code,
                'name' => $bill->supplier->name,
            ] : null,
            'total' => (float) $bill->total,
            'amount_paid' => (float) $bill->amount_paid,
            'balance_due' => round($bill->balanceDue(), 4),
            'days_overdue' => (int) $daysOverdue,
            'paid_state' => $bill->paidState(),
        ];
    }

    public function agingRows(int $companyId): array
    {
        $rows = $this->payableRows($companyId);
        $suppliers = collect();

        foreach ($rows as $row) {
            $key = $row['supplier_id'];

            if (! $suppliers->has($key)) {
                $suppliers[$key] = [
                    'supplier_id' => $key,
                    'supplier' => $row['supplier'],
                    'current' => 0.0,
                    'b1_30' => 0.0,
                    'b31_60' => 0.0,
                    'b61_90' => 0.0,
                    'b90' => 0.0,
                    'total' => 0.0,
                    'bills' => [],
                ];
            }

            $supplier = $suppliers[$key];
            $supplier[$this->bucketFor($row['days_overdue'])] = round($supplier[$this->bucketFor($row['days_overdue'])] + $row['balance_due'], 4);
            $supplier['total'] = round($supplier['total'] + $row['balance_due'], 4);
            $supplier['bills'][] = $row;
            $suppliers[$key] = $supplier;
        }

        return $suppliers->sortByDesc('total')->values()->all();
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
        $rows = $this->payableRows($companyId);

        $totalPayable = round($rows->sum('balance_due'), 4);
        $overdue = round($rows->where('days_overdue', '>', 0)->sum('balance_due'), 4);

        $startOfMonth = now()->startOfMonth()->toDateString();
        $paidThisMonth = SupplierPayment::query()
            ->where('company_id', $companyId)
            ->where('payment_date', '>=', $startOfMonth)
            ->sum('amount');

        $advanceBalance = SupplierPayment::query()
            ->where('company_id', $companyId)
            ->where('type', SupplierPaymentType::Advance->value)
            ->get()
            ->sum(fn (SupplierPayment $payment) => $payment->advanceBalance());

        $topSuppliers = $rows
            ->groupBy('supplier_id')
            ->map(fn ($group) => [
                'supplier_id' => $group->first()['supplier']['id'],
                'supplier' => $group->first()['supplier'],
                'balance_due' => round($group->sum('balance_due'), 4),
                'bill_count' => $group->count(),
            ])
            ->sortByDesc('balance_due')
            ->take(5)
            ->values()
            ->all();

        $recentPayments = SupplierPayment::query()
            ->with('supplier:id,name,code')
            ->where('company_id', $companyId)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return [
            'total_payable' => $totalPayable,
            'overdue' => $overdue,
            'paid_this_month' => round((float) $paidThisMonth, 4),
            'advance_balance' => round($advanceBalance, 4),
            'top_suppliers' => $topSuppliers,
            'recent_payments' => $recentPayments->map(function (SupplierPayment $payment) {
                return [
                    'id' => $payment->id,
                    'type' => $payment->type,
                    'payment_no' => $payment->payment_no,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'supplier' => $payment->supplier ? [
                        'id' => $payment->supplier->id,
                        'code' => $payment->supplier->code,
                        'name' => $payment->supplier->name,
                    ] : null,
                    'amount' => (float) $payment->amount,
                    'applied_amount' => $payment->appliedAmount(),
                ];
            })->values()->all(),
        ];
    }
}