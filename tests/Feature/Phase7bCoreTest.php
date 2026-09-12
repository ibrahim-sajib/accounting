<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\Payroll\Models\Department;
use App\Domain\Payroll\Models\Designation;
use App\Domain\Payroll\Models\Employee;
use App\Domain\Payroll\Models\PayrollRun;
use App\Domain\Payroll\Models\SalaryPayment;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7bCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $salaryExpenseGl;

    private int $salaryPayableGl;

    private int $deductionsPayableGl;

    private int $cashGl;

    private int $bankGl;

    private BankAccount $bankAccount;

    private Department $department;

    private Designation $designation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->cashGl = $setting->default_cash_account_id;
        $this->bankGl = $setting->default_bank_account_id;

        $this->bankAccount = BankAccount::query()->create([
            'company_id' => 1,
            'account_name' => 'Payroll Bank A/C',
            'account_no' => 'PB-001',
            'bank_name' => 'Dutch Bangla Bank',
            'gl_account_id' => $this->bankGl,
            'is_active' => true,
        ]);

        $this->salaryExpenseGl = Account::query()->where('company_id', 1)->where('code', '5111')->value('id');
        $this->salaryPayableGl = Account::query()->where('company_id', 1)->where('code', '2141')->value('id');
        $this->deductionsPayableGl = Account::query()->where('company_id', 1)->where('code', '2143')->value('id');

        $this->department = Department::query()->where('company_id', 1)->orderBy('id')->firstOrFail();
        $this->designation = Designation::query()->where('company_id', 1)->orderBy('id')->firstOrFail();
    }

    protected function openPeriod(): AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('status', 'open')
            ->orderBy('start_date')
            ->firstOrFail();
    }

    protected function employeePayload(string $email, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tanvir Ahmed',
            'email' => $email,
            'phone' => '0171'.substr((string) mt_rand(100000, 999999), 0, 5),
            'join_date' => '2026-01-05',
            'department_id' => $this->department->id,
            'designation_id' => $this->designation->id,
            'is_active' => true,
            'basic' => '50000.0000',
            'house_rent_allowance' => '20000.0000',
            'medical_allowance' => '5000.0000',
            'travel_allowance' => '5000.0000',
            'other_allowance' => '0',
            'income_tax_deduction' => '10000.0000',
            'provident_fund_deduction' => '5000.0000',
            'other_deduction' => '0',
        ], $overrides);
    }

    protected function registerEmployee(string $email, array $overrides = []): Employee
    {
        $this->post('/payroll/employees', $this->employeePayload($email, $overrides))->assertRedirect();

        return Employee::query()->where('company_id', 1)->orderByDesc('id')->firstOrFail();
    }

    protected function lastRun(): PayrollRun
    {
        return PayrollRun::query()->where('company_id', 1)->orderByDesc('id')->firstOrFail();
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_payroll_pages_render_for_super_admin(): void
    {
        $this->get('/payroll')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Index')
                ->has('runs.data')
                ->has('periods'));

        $this->get('/payroll/employees')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Employees')
                ->has('employees.data')
                ->has('departments')
                ->has('designations'));

        $this->get('/payroll/employees/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/EmployeesCreate')
                ->has('departments')
                ->has('designations'));

        $employee = $this->registerEmployee('pages@employee.test');

        $this->get("/payroll/employees/{$employee->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/EmployeeShow')
                ->has('employee')
                ->has('employee.salaryStructure')
                ->has('employee.department')
                ->where('employee.salaryStructure.basic', '50000.0000')
                ->has('runs'));

        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();

        $this->get('/payroll/runs/'.$this->lastRun()->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/RunShow')
                ->has('run')
                ->has('lines')
                ->has('payments')
                ->has('cashAccounts')
                ->has('bankAccounts')
                ->has('paymentMethods'));
    }

    // ───────────────────────── Sub-resources ─────────────────────────

    public function test_department_and_designation_management(): void
    {
        $this->post('/payroll/departments', ['name' => 'Legal'])
            ->assertRedirect();
        $this->assertTrue(Department::query()->where('company_id', 1)->where('name', 'Legal')->exists());

        $dept = Department::query()->where('company_id', 1)->where('name', 'Legal')->firstOrFail();
        $this->put("/payroll/departments/{$dept->id}", ['name' => 'Legal Affairs'])->assertRedirect();
        $this->assertSame('Legal Affairs', $dept->fresh()->name);

        $this->post('/payroll/designations', ['name' => 'Junior Officer'])->assertRedirect();
        $dsg = Designation::query()->where('company_id', 1)->where('name', 'Junior Officer')->firstOrFail();
        $this->put("/payroll/designations/{$dsg->id}", ['name' => 'Officer Trainee'])->assertRedirect();
        $this->assertSame('Officer Trainee', $dsg->fresh()->name);

        $this->delete("/payroll/designations/{$dsg->id}")->assertRedirect();
        $this->assertSoftDeleted('designations', ['id' => $dsg->id]);
    }

    public function test_department_in_use_deactivates_instead_of_deleting(): void
    {
        $this->post('/payroll/departments', ['name' => 'Import'])->assertRedirect();
        $dept = Department::query()->where('company_id', 1)->where('name', 'Import')->firstOrFail();

        $this->registerEmployee('inuse@employee.test', ['department_id' => $dept->id]);

        $this->delete("/payroll/departments/{$dept->id}")->assertRedirect();

        $dept->refresh();
        $this->assertFalse($dept->is_active);
        $this->assertNotSoftDeleted($dept);
    }

    // ───────────────────────── Employees ─────────────────────────

    public function test_employee_registration_persists_salary_structure(): void
    {
        $this->post('/payroll/employees', $this->employeePayload('register@employee.test'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $employee = Employee::query()->where('company_id', 1)->where('email', 'register@employee.test')->firstOrFail();
        $ss = $employee->salaryStructure;

        $this->assertSame('50000.0000', $ss->basic);
        $this->assertSame(80000.0, $ss->grossPay());
        $this->assertSame(15000.0, $ss->deductionsTotal());
        $this->assertSame(65000.0, $ss->netPay());
    }

    public function test_employee_registration_validates_numeric_fields(): void
    {
        $this->post('/payroll/employees', $this->employeePayload('bad@employee.test', ['basic' => '', 'house_rent_allowance' => '-5']))
            ->assertSessionHasErrors(['basic']);
    }

    public function test_employee_update_changes_structure_and_details(): void
    {
        $employee = $this->registerEmployee('update@employee.test');

        $this->put("/payroll/employees/{$employee->id}", $this->employeePayload('update@employee.test', [
            'name' => 'Tanvir Rahman',
            'basic' => '60000.0000',
            'medical_allowance' => '0',
            'income_tax_deduction' => '12000.0000',
        ]))->assertRedirect();

        $employee->refresh();
        $this->assertSame('Tanvir Rahman', $employee->name);
        $this->assertSame(85000.0, $employee->salaryStructure->grossPay());
        $this->assertSame(17000.0, $employee->salaryStructure->deductionsTotal());
        $this->assertSame(68000.0, $employee->salaryStructure->netPay());
    }

    public function test_employee_delete_refused_once_in_a_run(): void
    {
        $employee = $this->registerEmployee('guarded@employee.test');
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();

        $this->delete("/payroll/employees/{$employee->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('employees', ['id' => $employee->id]);
    }

    // ───────────────────────── Processing ─────────────────────────

    public function test_process_payroll_creates_draft_run_with_computed_lines(): void
    {
        $this->registerEmployee('a@run.test');
        $this->registerEmployee('b@run.test', [
            'name' => 'Ridwan Karim',
            'basic' => '30000.0000',
            'house_rent_allowance' => '10000.0000',
            'income_tax_deduction' => '4000.0000',
        ]);

        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $run = $this->lastRun();
        $this->assertSame(TransactionStatus::Draft->value, $run->status);
        $this->assertNull($run->run_no);
        $this->assertSame(2, $run->lines()->count());
        $this->assertSame(130000.0, (float) $run->total_gross);
        $this->assertSame(24000.0, (float) $run->total_deductions);
        $this->assertSame(106000.0, (float) $run->total_net);
    }

    public function test_process_payroll_refused_when_run_exists_for_period(): void
    {
        $this->registerEmployee('dup@run.test');
        $period = $this->openPeriod();

        $this->post('/payroll/process', ['period_id' => $period->id])->assertRedirect();
        $this->post('/payroll/process', ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, PayrollRun::query()->where('company_id', 1)->where('period_id', $period->id)->count());
    }

    public function test_line_adjust_recomputes_totals_but_posted_lines_are_locked(): void
    {
        $employee = $this->registerEmployee('adj@run.test');
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();

        $run = $this->lastRun();
        $line = $run->lines()->where('employee_id', $employee->id)->firstOrFail();

        $this->put("/payroll/runs/{$run->id}/lines/{$line->id}", [
            'gross_pay' => '90000.0000',
            'deductions_total' => '20000.0000',
        ])->assertRedirect();

        $line->refresh();
        $run->refresh();
        $this->assertSame(90000.0, (float) $line->gross_pay);
        $this->assertSame(70000.0, (float) $line->net_pay);
        $this->assertSame(90000.0, (float) $run->total_gross);
        $this->assertSame(70000.0, (float) $run->total_net);

        $this->post("/payroll/runs/{$run->id}/post")->assertRedirect();

        $this->put("/payroll/runs/{$run->id}/lines/{$line->id}", [
            'gross_pay' => '1',
            'deductions_total' => '0',
        ])->assertRedirect()
            ->assertSessionHas('error');
    }

    // ───────────────────────── Posting ─────────────────────────

    public function test_post_run_books_accrual_journal_and_assigns_number(): void
    {
        $employee = $this->registerEmployee('post@run.test');
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();

        $run = $this->lastRun();

        $this->post("/payroll/runs/{$run->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success');

        $run->refresh();
        $this->assertSame(TransactionStatus::Posted->value, $run->status);
        $this->assertSame('PR-2026-0001', $run->run_no);

        $journal = Journal::query()->where('company_id', 1)->where('id', $run->journal_id)->firstOrFail();
        $this->assertSame(JournalSourceType::Payroll->value, $journal->source_type);
        $this->assertSame($run->id, $journal->source_id);
        $this->assertSame(TransactionStatus::Posted->value, $journal->status);
        $this->assertSame('PYR-2026-0001', $journal->journal_no);

        $lines = $journal->lines()->get();
        $this->assertSame(80000.0, (float) $lines->where('account_id', $this->salaryExpenseGl)->sum('debit'));
        $this->assertSame(65000.0, (float) $lines->where('account_id', $this->salaryPayableGl)->sum('credit'));
        $this->assertSame(15000.0, (float) $lines->where('account_id', $this->deductionsPayableGl)->sum('credit'));
        $this->assertSame(80000.0, (float) $lines->sum('debit'));
        $this->assertSame(80000.0, (float) $lines->sum('credit'));
    }

    public function test_post_run_twice_is_refused(): void
    {
        $this->registerEmployee('rep@run.test');
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();
        $run = $this->lastRun();

        $this->post("/payroll/runs/{$run->id}/post")->assertRedirect();
        $this->post("/payroll/runs/{$run->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Journal::query()->where('company_id', 1)->where('source_type', 'payroll')->where('source_id', $run->id)->count());
    }

    // ───────────────────────── Salary payments ─────────────────────────

    public function test_salary_payment_on_posted_run_books_pmt_journal(): void
    {
        $employee = $this->registerEmployee('pay@run.test');
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();
        $run = $this->lastRun();
        $this->post("/payroll/runs/{$run->id}/post")->assertRedirect();

        $this->post("/payroll/runs/{$run->id}/payments", [
            'payment_method' => 'bank',
            'bank_account_id' => $this->bankAccount->id,
            'amount' => '25000.0000',
            'payment_date' => $run->run_date->toDateString(),
        ])->assertRedirect()
            ->assertSessionHas('success');

        $payment = SalaryPayment::query()->where('company_id', 1)->where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertSame('SP-2026-0001', $payment->payment_no);

        $journal = Journal::query()->findOrFail($payment->journal_id);
        $this->assertSame(JournalSourceType::Payroll->value, $journal->source_type);
        $this->assertSame(TransactionStatus::Posted->value, $journal->status);

        $this->assertSame(25000.0, (float) $journal->lines()->where('account_id', $this->salaryPayableGl)->sum('debit'));
        $this->assertSame(25000.0, (float) $journal->lines()->where('account_id', $this->bankGl)->sum('credit'));

        $run->refresh();
        $this->assertSame(25000.0, $run->paidTotal());
        $this->assertSame(40000.0, $run->remainingPayable());
        $this->assertSame('partial', $run->paidState());

        $this->get('/payroll/runs/'.$run->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Payroll/RunShow')->has('payments', 1));
    }

    public function test_salary_payment_refused_on_draft_run_or_overpayment(): void
    {
        $employee = $this->registerEmployee('guard@run.test');
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();
        $run = $this->lastRun();

        $payload = [
            'payment_method' => 'bank',
            'bank_account_id' => $this->bankAccount->id,
            'amount' => '100.0000',
            'payment_date' => $run->run_date->toDateString(),
        ];

        $this->post("/payroll/runs/{$run->id}/payments", $payload)
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(0, SalaryPayment::query()->where('company_id', 1)->count());

        $this->post("/payroll/runs/{$run->id}/post")->assertRedirect();

        $this->post("/payroll/runs/{$run->id}/payments", array_merge($payload, ['amount' => '999999.0000']))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(0, SalaryPayment::query()->where('company_id', 1)->count());
    }

    // ───────────────────────── Permissions ─────────────────────────

    public function test_accountant_can_view_and_post_but_not_process_or_manage(): void
    {
        $this->registerEmployee('acct@run.test');
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertRedirect();
        $run = $this->lastRun();

        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($user);

        $this->get('/payroll')->assertOk();
        $this->get('/payroll/runs/'.$run->id)->assertOk();
        $this->get('/payroll/employees')->assertOk();
        $this->get('/payroll/employees/create')->assertForbidden();
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertForbidden();

        $this->post("/payroll/runs/{$run->id}/post")->assertRedirect();
        $this->assertSame(TransactionStatus::Posted->value, $run->fresh()->status);
    }

    public function test_viewer_is_readonly(): void
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        $this->actingAs($user);

        $this->get('/payroll')->assertOk();
        $this->get('/payroll/employees')->assertOk();
        $this->get('/payroll/employees/create')->assertForbidden();
        $this->post('/payroll/process', ['period_id' => $this->openPeriod()->id])->assertForbidden();
    }
}