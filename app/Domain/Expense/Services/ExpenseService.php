<?php

namespace App\Domain\Expense\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Expense\Exceptions\ExpensePostingException;
use App\Domain\Expense\Models\Expense;
use App\Domain\Expense\Models\ExpenseCategory;
use App\Domain\Tax\Models\TaxRate;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Expense engine (module 19): draft → posted EXP journal. Expense account Dr |
 * Input Tax Dr | Cash/Bank/Payable Cr, plus recurring draft generation.
 */
class ExpenseService
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    /**
     * @param  array{category_id: int, payee: string, expense_date: string, amount: float|string, tax_rate_id?: ?int, payment_method: string, cash_account_id?: ?int, bank_account_id?: ?int, supplier_id?: ?int, reference?: ?string, notes?: ?string, is_recurring?: bool, recurrence_frequency?: ?string, next_generation_date?: ?string}  $data
     */
    public function store(array $data, int $companyId): Expense
    {
        return DB::transaction(function () use ($data, $companyId) {
            $amount = round((float) $data['amount'], 4);
            $tax = $this->computeTax($amount, $data['tax_rate_id'] ?? null);

            $this->assertPaymentAccounts($data, $companyId);

            return Expense::query()->create([
                'company_id' => $companyId,
                'branch_id' => session('active_branch_id'),
                'category_id' => $data['category_id'],
                'payee' => $data['payee'],
                'expense_date' => $data['expense_date'],
                'amount' => $amount,
                'tax_rate_id' => $data['tax_rate_id'] ?? null,
                'tax_amount' => $tax,
                'payment_method' => $data['payment_method'],
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'payable_account_id' => $this->resolvePayableAccount($data, $companyId),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => TransactionStatus::Draft->value,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_frequency' => $data['is_recurring'] ? ($data['recurrence_frequency'] ?? null) : null,
                'next_generation_date' => $data['is_recurring'] ? ($data['next_generation_date'] ?? $this->nextRun($data['expense_date'], $data['recurrence_frequency'] ?? 'monthly')) : null,
            ]);
        });
    }

    /**
     * @param  array{category_id: int, payee: string, expense_date: string, amount: float|string, tax_rate_id?: ?int, payment_method: string, cash_account_id?: ?int, bank_account_id?: ?int, supplier_id?: ?int, reference?: ?string, notes?: ?string, is_recurring?: bool, recurrence_frequency?: ?string, next_generation_date?: ?string}  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        if ($expense->isPosted()) {
            throw new ExpensePostingException('Posted expenses cannot be changed.');
        }

        $amount = round((float) $data['amount'], 4);
        $tax = $this->computeTax($amount, $data['tax_rate_id'] ?? null);

        $this->assertPaymentAccounts($data, $expense->company_id);

        $expense->update([
            'category_id' => $data['category_id'],
            'payee' => $data['payee'],
            'expense_date' => $data['expense_date'],
            'amount' => $amount,
            'tax_rate_id' => $data['tax_rate_id'] ?? null,
            'tax_amount' => $tax,
            'payment_method' => $data['payment_method'],
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'payable_account_id' => $this->resolvePayableAccount($data, $expense->company_id),
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_frequency' => $data['is_recurring'] ? ($data['recurrence_frequency'] ?? null) : null,
            'next_generation_date' => $data['is_recurring'] ? ($data['next_generation_date'] ?? $this->nextRun($data['expense_date'], $data['recurrence_frequency'] ?? 'monthly')) : null,
            'updated_by' => Auth::id(),
        ]);

        return $expense;
    }

    public function post(Expense $expense): Expense
    {
        if ($expense->isPosted()) {
            throw new ExpensePostingException('This expense is already posted.');
        }

        $lines = $this->buildJournalLines($expense);

        $this->postingService->assertBalanced($lines);

        if (count($lines) < 2) {
            throw new ExpensePostingException('The expense must produce at least two journal lines.');
        }

        return DB::transaction(function () use ($expense, $lines) {
            $expenseNo = $this->nextExpenseNo($expense->company_id, $expense->expense_date);

            $journal = Journal::query()->create([
                'company_id' => $expense->company_id,
                'branch_id' => $expense->branch_id ?? session('active_branch_id'),
                'period_id' => $this->periodFor($expense->company_id, $expense->expense_date->toDateString())?->id,
                'journal_date' => $expense->expense_date,
                'source_type' => JournalSourceType::Expense->value,
                'source_id' => $expense->id,
                'reference' => $expenseNo,
                'description' => 'Expense '.$expenseNo,
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

            $expense->update([
                'expense_no' => $expenseNo,
                'status' => TransactionStatus::Posted->value,
                'journal_id' => $journal->id,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return $expense->fresh();
        });
    }

    public function destroy(Expense $expense): void
    {
        if ($expense->isPosted()) {
            throw new ExpensePostingException('Posted expenses cannot be deleted.');
        }

        $expense->delete();
    }

    /**
     * Generate the next DRAFT copy for every posted recurring expense whose
     * next_generation_date has arrived. Copies are created as drafts and the
     * schedule advances, so they must be posted/edited manually.
     *
     * @return array{created: int<0, max>}
     */
    public function generateRecurringDrafts(): array
    {
        $due = Expense::query()
            ->where('is_recurring', true)
            ->where('status', TransactionStatus::Posted->value)
            ->whereNotNull('next_generation_date')
            ->whereDate('next_generation_date', '<=', now()->toDateString())
            ->get();

        $created = 0;

        foreach ($due as $expense) {
            DB::transaction(function () use ($expense, &$created) {
                Expense::query()->create([
                    'company_id' => $expense->company_id,
                    'branch_id' => $expense->branch_id,
                    'category_id' => $expense->category_id,
                    'payee' => $expense->payee,
                    'expense_date' => $expense->next_generation_date,
                    'amount' => $expense->amount,
                    'tax_rate_id' => $expense->tax_rate_id,
                    'tax_amount' => $expense->tax_amount,
                    'payment_method' => $expense->payment_method,
                    'cash_account_id' => $expense->cash_account_id,
                    'bank_account_id' => $expense->bank_account_id,
                    'supplier_id' => $expense->supplier_id,
                    'payable_account_id' => $expense->payable_account_id,
                    'reference' => $expense->reference,
                    'notes' => 'Recurring copy from '.$expense->expense_no,
                    'status' => TransactionStatus::Draft->value,
                    'is_recurring' => true,
                    'recurrence_frequency' => $expense->recurrence_frequency,
                    'next_generation_date' => $this->nextRun(
                        $expense->next_generation_date->toDateString(),
                        $expense->recurrence_frequency ?? 'monthly'
                    ),
                ]);

                // Keep the chain on the source too so its schedule page stays accurate.
                $expense->update([
                    'next_generation_date' => $this->nextRun(
                        $expense->next_generation_date->toDateString(),
                        $expense->recurrence_frequency ?? 'monthly'
                    ),
                    'updated_by' => Auth::id(),
                ]);

                $created++;
            });
        }

        return ['created' => $created];
    }

    // ─── journal construction ───────────────────────────────────────────────

    /**
     * @return list<array{account_id: int, party_type: ?string, party_id: ?int, description: string, debit: float, credit: float}>
     */
    public function buildJournalLines(Expense $expense): array
    {
        $reference = $expense->reference ?? $expense->expense_no;
        $total = $expense->total();

        $expenseAccount = $this->expenseAccountFor($expense);

        $lines = [];

        $lines[] = $this->line($expenseAccount, 'Expense '.($reference ? '— '.$reference : ''), $expense->amount, 0, null, null);

        if ($expense->tax_amount > 0) {
            $lines[] = $this->line($this->inputTaxAccountFor($expense), 'Expense input tax', $expense->tax_amount, 0, null, null);
        }

        $lines[] = $this->creditLine($expense, $total, $reference);

        return $lines;
    }

    protected function creditLine(Expense $expense, float $total, ?string $reference): array
    {
        $method = $expense->paymentMethod();

        if ($method === null) {
            throw new ExpensePostingException('Unknown payment method.');
        }

        return match (true) {
            $method->isPayable() => $this->line($this->payableAccountFor($expense), 'Expense payable', 0, $total, 'supplier', $expense->supplier_id),
            $method === \App\Support\Enums\ExpensePaymentMethod::Bank => $this->line($this->bankGl($expense->bank_account_id, $expense->company_id), 'Expense paid via bank', 0, $total, null, null),
            default => $this->line($this->cashGl($expense->cash_account_id, $expense->company_id), 'Expense paid via cash', 0, $total, null, null),
        };
    }

    protected function line(int $accountId, string $description, float $debit, float $credit, ?string $partyType, ?int $partyId): array
    {
        return [
            'account_id' => $accountId,
            'party_type' => $partyType,
            'party_id' => $partyId,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    protected function expenseAccountFor(Expense $expense): int
    {
        $category = ExpenseCategory::query()->where('company_id', $expense->company_id)->find($expense->category_id);

        if (! $category || $category->is_active === false) {
            throw new ExpensePostingException('The expense category is missing or inactive.');
        }

        return $this->postable($category->expense_account_id, 'The category has no postable expense account.');
    }

    protected function inputTaxAccountFor(Expense $expense): int
    {
        $rate = $expense->tax_rate_id ? TaxRate::query()->find($expense->tax_rate_id) : null;

        $accountId = $rate?->input_account_id
            ?? \App\Domain\Accounting\Models\AccountingSetting::query()
                ->where('company_id', $expense->company_id)
                ->value('default_tax_input_account_id');

        return $this->postable((int) $accountId, 'There is no postable input tax account for this expense.');
    }

    protected function payableAccountFor(Expense $expense): int
    {
        if ($expense->payable_account_id) {
            return $this->postable($expense->payable_account_id, 'The payable account is not postable.');
        }

        $accountId = $expense->supplier?->ap_account_id
            ?? \App\Domain\Accounting\Models\AccountingSetting::query()
                ->where('company_id', $expense->company_id)
                ->value('default_ap_account_id');

        return $this->postable((int) $accountId, 'There is no postable accounts payable account for this expense.');
    }

    protected function cashGl(?int $cashAccountId, int $companyId): int
    {
        $account = $cashAccountId ? CashAccount::query()->where('company_id', $companyId)->find($cashAccountId) : null;

        if (! $account || $account->is_active === false) {
            throw new ExpensePostingException('The selected cash account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The cash account has no postable GL account.');
    }

    protected function bankGl(?int $bankAccountId, int $companyId): int
    {
        $account = $bankAccountId ? BankAccount::query()->where('company_id', $companyId)->find($bankAccountId) : null;

        if (! $account || $account->is_active === false) {
            throw new ExpensePostingException('The selected bank account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The bank account has no postable GL account.');
    }

    protected function postable(int $accountId, string $message): int
    {
        $account = Account::query()->find($accountId);

        if (! $account || ! $account->is_postable || ! $account->is_active) {
            throw new ExpensePostingException($message);
        }

        return $accountId;
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    protected function computeTax(float $amount, ?int $taxRateId): float
    {
        if (! $taxRateId || $amount <= 0) {
            return 0.0;
        }

        $rate = TaxRate::query()->find($taxRateId);

        if (! $rate) {
            return 0.0;
        }

        return round($amount * ((float) $rate->rate_percent / 100), 4);
    }

    protected function resolvePayableAccount(array $data, int $companyId): ?int
    {
        if (($data['payment_method'] ?? '') !== 'payable') {
            return null;
        }

        if (! empty($data['supplier_id'])) {
            $supplier = \App\Domain\Party\Models\Supplier::query()->where('company_id', $companyId)->find($data['supplier_id']);

            if ($supplier?->ap_account_id) {
                return $supplier->ap_account_id;
            }
        }

        return \App\Domain\Accounting\Models\AccountingSetting::query()
            ->where('company_id', $companyId)
            ->value('default_ap_account_id');
    }

    protected function assertPaymentAccounts(array $data, int $companyId): void
    {
        $method = $data['payment_method'] ?? '';

        if ($method === 'cash' && empty($data['cash_account_id'])) {
            throw new ExpensePostingException('A cash account is required for cash expenses.');
        }

        if ($method === 'bank' && empty($data['bank_account_id'])) {
            throw new ExpensePostingException('A bank account is required for bank expenses.');
        }

        if ($method === 'payable' && empty($data['supplier_id'])) {
            throw new ExpensePostingException('A supplier is required for payable expenses.');
        }
    }

    public function nextExpenseNo(int $companyId, ?string $date): string
    {
        $year = $date ? Carbon::parse($date)->format('Y') : date('Y');

        $latest = Expense::query()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('expense_no', 'like', "EXP-{$year}-%")
            ->orderByDesc('expense_no')
            ->value('expense_no');

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("EXP-{$year}-")->toString()) + 1;
        }

        return sprintf('EXP-%s-%04d', $year, $sequence);
    }

    protected function periodFor(int $companyId, string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    public function nextRun(string $fromDate, string $frequency): string
    {
        $base = Carbon::parse($fromDate);

        return match ($frequency) {
            'weekly' => $base->copy()->addWeek()->toDateString(),
            'yearly' => $base->copy()->addYear()->toDateString(),
            default => $base->copy()->addMonth()->toDateString(),
        };
    }
}