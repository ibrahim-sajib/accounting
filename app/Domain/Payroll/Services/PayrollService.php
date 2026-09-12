<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Payroll\Exceptions\PayrollPostingException;
use App\Domain\Payroll\Models\Employee;
use App\Domain\Payroll\Models\PayrollRun;
use App\Domain\Payroll\Models\PayrollRunLine;
use App\Domain\Payroll\Models\SalaryPayment;
use App\Domain\Payroll\Models\SalaryStructure;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\SalaryPaymentMethod;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

/**
 * Payroll engine (module 22): employees + salary structures →
 * process a payroll run for a period (draft) → review lines →
 * post the accrual (PYR journal: Salary Expense Dr | Salary Payable Cr) →
 * pay salaries (Salary Payable Dr | Cash/Bank Cr).
 */
class PayrollService
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    // ─── employees + salary structures ──────────────────────────────────────

    /**
     * @param  array{name: string, email?: ?string, phone?: ?string, department_id: int, designation_id: int, join_date: string, is_active: bool, basic: float|string, house_rent_allowance?: float|string, medical_allowance?: float|string, travel_allowance?: float|string, other_allowance?: float|string, income_tax_deduction?: float|string, provident_fund_deduction?: float|string, other_deduction?: float|string}  $data
     */
    public function storeEmployee(array $data, int $companyId): Employee
    {
        return DB::transaction(function () use ($data, $companyId) {
            $employee = Employee::query()->create([
                'company_id' => $companyId,
                'branch_id' => session('active_branch_id'),
                'department_id' => $data['department_id'],
                'designation_id' => $data['designation_id'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'join_date' => $data['join_date'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $structure = SalaryStructure::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'basic' => $data['basic'],
                'house_rent_allowance' => $data['house_rent_allowance'] ?? 0,
                'medical_allowance' => $data['medical_allowance'] ?? 0,
                'travel_allowance' => $data['travel_allowance'] ?? 0,
                'other_allowance' => $data['other_allowance'] ?? 0,
                'income_tax_deduction' => $data['income_tax_deduction'] ?? 0,
                'provident_fund_deduction' => $data['provident_fund_deduction'] ?? 0,
                'other_deduction' => $data['other_deduction'] ?? 0,
            ]);

            $employee->update(['updated_by' => Auth::id()]);

            return $employee->fresh(['department', 'designation', 'salaryStructure']);
        });
    }

    /**
     * @param  array{name: string, email?: ?string, phone?: ?string, department_id: int, designation_id: int, join_date: string, is_active: bool, basic: float|string, house_rent_allowance?: float|string, medical_allowance?: float|string, travel_allowance?: float|string, other_allowance?: float|string, income_tax_deduction?: float|string, provident_fund_deduction?: float|string, other_deduction?: float|string}  $data
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->update([
                'department_id' => $data['department_id'],
                'designation_id' => $data['designation_id'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'join_date' => $data['join_date'],
                'is_active' => (bool) ($data['is_active'] ?? $employee->is_active),
            ]);

            $structure = $employee->salaryStructure;

            if (! $structure) {
                $structure = new SalaryStructure([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                ]);
            }

            $structure->fill([
                'basic' => $data['basic'],
                'house_rent_allowance' => $data['house_rent_allowance'] ?? 0,
                'medical_allowance' => $data['medical_allowance'] ?? 0,
                'travel_allowance' => $data['travel_allowance'] ?? 0,
                'other_allowance' => $data['other_allowance'] ?? 0,
                'income_tax_deduction' => $data['income_tax_deduction'] ?? 0,
                'provident_fund_deduction' => $data['provident_fund_deduction'] ?? 0,
                'other_deduction' => $data['other_deduction'] ?? 0,
            ])->save();

            return $employee->fresh(['department', 'designation', 'salaryStructure']);
        });
    }

    public function destroyEmployee(Employee $employee): void
    {
        if ($employee->referencedByPayroll()) {
            throw new PayrollPostingException('This employee is referenced by a payroll run and cannot be deleted.');
        }

        $employee->delete();
    }

    // ─── payroll run lifecycle ──────────────────────────────────────────────

    /**
     * Process payroll: create a DRAFT run for a period, one line per active
     * employee with a salary structure, amounts computed from the structure.
     *
     * @param  array{period_id: int}  $data
     */
    public function processPayroll(array $data, int $companyId): PayrollRun
    {
        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->findOrFail($data['period_id']);

        if (PayrollRun::query()->where('company_id', $companyId)->where('period_id', $period->id)->exists()) {
            throw new PayrollPostingException('A payroll run already exists for this period.');
        }

        $employees = Employee::query()
            ->with(['salaryStructure'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Employee $e) => $e->salaryStructure instanceof SalaryStructure)
            ->values();

        if ($employees->isEmpty()) {
            throw new PayrollPostingException('There are no active employees with a salary structure to process.');
        }

        return DB::transaction(function () use ($employees, $period, $companyId) {
            $rows = collect($employees)->map(fn (Employee $e) => [
                'employee_id' => $e->id,
                'gross_pay' => $e->salaryStructure->grossPay(),
                'allowances_total' => $e->salaryStructure->allowancesTotal(),
                'deductions_total' => $e->salaryStructure->deductionsTotal(),
                'net_pay' => $e->salaryStructure->netPay(),
            ]);

            $run = PayrollRun::query()->create([
                'company_id' => $companyId,
                'branch_id' => session('active_branch_id'),
                'period_id' => $period->id,
                'run_date' => $period->end_date,
                'status' => TransactionStatus::Draft->value,
                'total_gross' => $rows->sum('gross_pay'),
                'total_deductions' => $rows->sum('deductions_total'),
                'total_net' => $rows->sum('net_pay'),
            ]);

            $run->lines()->createMany($rows->map(fn (array $r) => [
                'company_id' => $companyId,
                'employee_id' => $r['employee_id'],
                'gross_pay' => $r['gross_pay'],
                'allowances_total' => $r['allowances_total'],
                'deductions_total' => $r['deductions_total'],
                'net_pay' => $r['net_pay'],
            ])->all());

            return $run->fresh(['period', 'lines.employee']);
        });
    }

    /**
     * Manual adjustment of a single draft run line (review step). Recomputes
     * the run totals server-side so the journal always equals the lines.
     *
     * @param  array{gross_pay: float|string, deductions_total: float|string}  $data
     */
    public function updateRunLine(PayrollRunLine $line, array $data, int $companyId): void
    {
        $this->assertOwnedBy($line, $companyId);

        if (! $line->run->isDraft()) {
            throw new PayrollPostingException('Only draft payroll runs can be adjusted.');
        }

        $gross = round((float) $data['gross_pay'], 4);
        $deductions = round((float) $data['deductions_total'], 4);
        $net = round($gross - $deductions, 4);

        if ($net < 0) {
            throw new PayrollPostingException('Deductions cannot exceed gross pay.');
        }

        DB::transaction(function () use ($line, $gross, $deductions, $net) {
            $basic = (float) ($line->employee->fresh()->salaryStructure?->basic ?? 0);

            $line->update([
                'gross_pay' => $gross,
                'allowances_total' => max(0, round($gross - $basic, 4)),
                'deductions_total' => $deductions,
                'net_pay' => $net,
            ]);

            $run = $line->run;
            $run->update([
                'total_gross' => $run->lines()->sum('gross_pay'),
                'total_deductions' => $run->lines()->sum('deductions_total'),
                'total_net' => $run->lines()->sum('net_pay'),
            ]);
        });
    }

    /**
     * Post the run — books the salary accrual journal. Amounts are recomputed
     * from the persisted lines; run_no is assigned at posting (§1.18).
     *
     * @return array{run: PayrollRun, journal: Journal}
     */
    public function postRun(PayrollRun $run, int $companyId): array
    {
        $this->assertOwnedBy($run, $companyId);

        if (! $run->isDraft()) {
            throw new PayrollPostingException('Only draft payroll runs can be posted.');
        }

        $lineRows = $run->lines()->get();

        if ($lineRows->isEmpty()) {
            throw new PayrollPostingException('A payroll run must have at least one employee line.');
        }

        $gross = round($lineRows->sum('gross_pay'), 4);
        $deductions = round($lineRows->sum('deductions_total'), 4);
        $net = round($lineRows->sum('net_pay'), 4);

        if (abs(($gross - $deductions) - $net) > 0.0001) {
            throw new PayrollPostingException('Payroll lines are not internally balanced.');
        }

        if ($gross <= 0) {
            throw new PayrollPostingException('The payroll run has no gross pay to post.');
        }

        $lines = [];

        $lines[] = $this->line(
            $this->salaryExpenseAccount($companyId),
            "Salaries — {$run->period->name}",
            $gross,
            0
        );

        $lines[] = $this->line(
            $this->salaryPayableAccount($companyId),
            "Salary payable — {$run->period->name}",
            0,
            $net
        );

        if ($deductions > 0) {
            $lines[] = $this->line(
                $this->deductionsPayableAccount($companyId),
                "Employee deductions — {$run->period->name}",
                0,
                $deductions
            );
        }

        $this->postingService->assertBalanced($lines);

        return DB::transaction(function () use ($run, $companyId, $gross, $deductions, $net, $lines) {
            $runNo = $this->nextRunNo($companyId, $run->run_date->year);

            $journal = Journal::query()->create([
                'company_id' => $companyId,
                'branch_id' => $run->branch_id ?? session('active_branch_id'),
                'period_id' => $run->period_id,
                'journal_date' => $run->run_date,
                'source_type' => JournalSourceType::Payroll->value,
                'source_id' => $run->id,
                'reference' => $runNo,
                'description' => "Salary accrual — {$run->period->name}",
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany(array_map(fn (array $l) => [
                'account_id' => $l['account_id'],
                'description' => $l['description'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ], $lines));

            $this->postingService->post($journal);

            $run->update([
                'run_no' => $runNo,
                'status' => TransactionStatus::Posted->value,
                'journal_id' => $journal->id,
                'total_gross' => $gross,
                'total_deductions' => $deductions,
                'total_net' => $net,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            return ['run' => $run->fresh(['period', 'journal']), 'journal' => $journal];
        });
    }

    public function destroyRun(PayrollRun $run, int $companyId): void
    {
        $this->assertOwnedBy($run, $companyId);

        if (! $run->isDraft()) {
            throw new PayrollPostingException('Only draft payroll runs can be deleted.');
        }

        DB::transaction(function () use ($run) {
            $run->lines()->delete();
            $run->delete();
        });
    }

    // ─── salary payments ────────────────────────────────────────────────────

    /**
     * Pay salaries from cash/bank — books Salary Payable Dr | Cash/Bank Cr.
     * Numbering SP-{year}-%04d is assigned at posting, like run_no (§1.18).
     *
     * @param  array{payment_method: string, amount: float|string, payment_date: string, cash_account_id?: ?int, bank_account_id?: ?int}  $data
     * @return array{payment: SalaryPayment, journal: Journal}
     */
    public function storeSalaryPayment(PayrollRun $run, array $data, int $companyId): array
    {
        $this->assertOwnedBy($run, $companyId);

        if (! $run->isPosted()) {
            throw new PayrollPostingException('Only posted payroll runs can be paid.');
        }

        $amount = round((float) $data['amount'], 4);

        if ($amount <= 0) {
            throw new PayrollPostingException('Payment amount must be positive.');
        }

        if ($amount > $run->remainingPayable() + 0.0001) {
            throw new PayrollPostingException('Payment exceeds the run\'s remaining salary payable.');
        }

        $method = SalaryPaymentMethod::from($data['payment_method']);

        $lines = [];
        $lines[] = $this->line(
            $this->salaryPayableAccount($companyId),
            "Salary payment — {$run->period->name}",
            $amount,
            0
        );

        $lines[] = $this->line(
            $this->paymentGl($method, $data, $companyId),
            'Salary payment via '.$method->label().' — '.$run->period->name,
            0,
            $amount
        );

        $this->postingService->assertBalanced($lines);

        return DB::transaction(function () use ($run, $companyId, $data, $method, $amount, $lines) {
            $paymentDate = \Illuminate\Support\Carbon::parse($data['payment_date']);
            $paymentNo = $this->nextPaymentNo($companyId, $paymentDate->year);

            $journal = Journal::query()->create([
                'company_id' => $companyId,
                'branch_id' => $run->branch_id ?? session('active_branch_id'),
                'period_id' => $this->periodFor($companyId, $paymentDate->toDateString())?->id,
                'journal_date' => $paymentDate,
                'source_type' => JournalSourceType::Payroll->value,
                'source_id' => $run->id,
                'reference' => $paymentNo,
                'description' => "Salary payment — {$run->period->name}",
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany(array_map(fn (array $l) => [
                'account_id' => $l['account_id'],
                'description' => $l['description'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ], $lines));

            $this->postingService->post($journal);

            $payment = SalaryPayment::query()->create([
                'company_id' => $companyId,
                'payroll_run_id' => $run->id,
                'payment_method' => $method->value,
                'cash_account_id' => $method === SalaryPaymentMethod::Cash ? ($data['cash_account_id'] ?? null) : null,
                'bank_account_id' => $method === SalaryPaymentMethod::Bank ? ($data['bank_account_id'] ?? null) : null,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_no' => $paymentNo,
                'journal_id' => $journal->id,
                'paid_at' => now(),
                'paid_by' => Auth::id(),
            ]);

            return ['payment' => $payment, 'journal' => $journal];
        });
    }

    // ─── journal construction ───────────────────────────────────────────────

    protected function line(int $accountId, string $description, float $debit, float $credit): array
    {
        return [
            'account_id' => $accountId,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    protected function paymentGl(SalaryPaymentMethod $method, array $data, int $companyId): int
    {
        return match ($method) {
            SalaryPaymentMethod::Cash => $this->cashGl($data['cash_account_id'] ?? null, $companyId),
            SalaryPaymentMethod::Bank => $this->bankGl($data['bank_account_id'] ?? null, $companyId),
        };
    }

    protected function cashGl(?int $cashAccountId, int $companyId): int
    {
        $account = $cashAccountId ? CashAccount::query()->where('company_id', $companyId)->find($cashAccountId) : null;

        if (! $account || $account->is_active === false) {
            throw new PayrollPostingException('The selected cash account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The cash account has no postable GL account.');
    }

    protected function bankGl(?int $bankAccountId, int $companyId): int
    {
        $account = $bankAccountId ? BankAccount::query()->where('company_id', $companyId)->find($bankAccountId) : null;

        if (! $account || $account->is_active === false) {
            throw new PayrollPostingException('The selected bank account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The bank account has no postable GL account.');
    }

    protected function salaryExpenseAccount(int $companyId): int
    {
        return $this->accountByCode($companyId, '5111', 'The Salaries & Wages expense account (5111) is not set up.');
    }

    protected function salaryPayableAccount(int $companyId): int
    {
        return $this->accountByCode($companyId, '2141', 'The Accrued Salaries payable account (2141) is not set up.');
    }

    protected function deductionsPayableAccount(int $companyId): int
    {
        return $this->accountByCode($companyId, '2143', 'The Employee Deductions Payable account (2143) is not set up.');
    }

    protected function accountByCode(int $companyId, string $code, string $message): int
    {
        $id = Account::query()->where('company_id', $companyId)->where('code', $code)->value('id');

        return $this->postable((int) $id, $message);
    }

    protected function postable(int $accountId, string $message): int
    {
        $account = Account::query()->find($accountId);

        if (! $account || ! $account->is_postable || ! $account->is_active) {
            throw new PayrollPostingException($message);
        }

        return $accountId;
    }

    // ─── numbering + helpers ────────────────────────────────────────────────

    protected function nextRunNo(int $companyId, int $year): string
    {
        $prefix = 'PR-'.$year.'-';
        $last = PayrollRun::query()
            ->where('company_id', $companyId)
            ->whereNotNull('run_no')
            ->where('run_no', 'like', $prefix.'%')
            ->max('run_no');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $seq);
    }

    protected function nextPaymentNo(int $companyId, int $year): string
    {
        $prefix = 'SP-'.$year.'-';
        $last = SalaryPayment::query()
            ->where('company_id', $companyId)
            ->whereNotNull('payment_no')
            ->where('payment_no', 'like', $prefix.'%')
            ->max('payment_no');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $seq);
    }

    protected function periodFor(int $companyId, string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    protected function assertOwnedBy(Model $model, int $companyId): void
    {
        if ((int) $model->company_id !== $companyId) {
            throw new PayrollPostingException('Record does not belong to the active company.');
        }
    }
}