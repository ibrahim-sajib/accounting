<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Budget\Models\Budget;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use App\Support\Enums\AccountType;
use App\Support\Enums\BudgetStatus;
use App\Support\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7cCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $expenseGl;

    private int $cashGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->cashGl = $setting->default_cash_account_id;

        $this->expenseGl = Account::query()->where('company_id', 1)->where('code', '5111')->value('id');
    }

    protected function openPeriod(): AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('status', 'open')
            ->orderBy('start_date')
            ->firstOrFail();
    }

    protected function budgetableAccount(): Account
    {
        return Account::query()
            ->where('company_id', 1)
            ->where('type', AccountType::Expense->value)
            ->where('is_postable', true)
            ->firstOrFail();
    }

    protected function incomeAccount(): Account
    {
        return Account::query()
            ->where('company_id', 1)
            ->where('type', AccountType::Income->value)
            ->where('is_postable', true)
            ->firstOrFail();
    }

    protected function nonBudgetableAccount(): Account
    {
        return Account::query()->where('company_id', 1)->findOrFail($this->cashGl);
    }

    protected function budgetPayload(array $lines = [], array $overrides = []): array
    {
        if ($lines === []) {
            $lines = [
                ['account_id' => $this->expenseGl, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '10000.0000'],
                ['account_id' => $this->incomeAccount()->id, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '5000.0000'],
            ];
        }

        return array_merge([
            'name' => 'FY Operating Budget',
            'fiscal_year_id' => $this->openPeriod()->fiscal_year_id,
            'notes' => 'Test budget',
            'lines' => $lines,
        ], $overrides);
    }

    protected function storeBudget(array $overrides = []): Budget
    {
        $this->post('/budgets', $this->budgetPayload([], $overrides))->assertRedirect();

        return Budget::query()->where('company_id', 1)->latest('id')->firstOrFail();
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_budget_pages_render_for_super_admin(): void
    {
        $this->get('/budgets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Index')
                ->has('budgets.data')
                ->has('statuses'));

        $this->get('/budgets/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Create')
                ->has('fiscalYears')
                ->has('accounts')
                ->has('periods'));
    }

    public function test_create_budget_persists_lines_and_total(): void
    {
        $budget = $this->storeBudget();

        $this->assertSame(BudgetStatus::Draft->value, $budget->status);
        $this->assertSame(2, $budget->lines()->count());
        $this->assertSame(15000.0, $budget->totalBudgeted());

        $this->get('/budgets/'.$budget->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Show')
                ->where('budget.name', 'FY Operating Budget')
                ->where('budget.total_budgeted', 15000)
                ->has('variance.rows', 2)
                ->has('variance.detail', 2));
    }

    // ───────────────────────── Validation ─────────────────────────

    public function test_duplicate_fiscal_year_budget_rejected(): void
    {
        $this->storeBudget();

        $this->post('/budgets', $this->budgetPayload())
            ->assertRedirect()
            ->assertSessionHas('error', 'A budget already exists for this fiscal year.');

        $this->assertSame(1, Budget::query()->where('company_id', 1)->count());
    }

    public function test_non_budgetable_account_rejected(): void
    {
        $lines = [
            ['account_id' => $this->nonBudgetableAccount()->id, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '5000.0000'],
        ];

        $this->post('/budgets', $this->budgetPayload($lines))
            ->assertRedirect()
            ->assertSessionHas('error', 'Budget lines may only target postable income or expense accounts.');

        $this->assertSame(0, Budget::query()->where('company_id', 1)->count());
    }

    public function test_period_outside_fiscal_year_rejected(): void
    {
        $otherFy = FiscalYear::query()->create([
            'company_id' => 1,
            'name' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_active' => false,
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        $otherPeriod = AccountingPeriod::query()->create([
            'fiscal_year_id' => $otherFy->id,
            'name' => 'Jan 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
            'is_active' => true,
            'status' => 'open',
        ]);

        $lines = [
            ['account_id' => $this->expenseGl, 'period_id' => $otherPeriod->id, 'budgeted_amount' => '100.0000'],
        ];

        $this->post('/budgets', $this->budgetPayload($lines))
            ->assertRedirect()
            ->assertSessionHas('error', 'A budget line references a period outside the selected fiscal year.');

        $this->assertSame(0, Budget::query()->where('company_id', 1)->count());
    }

    public function test_duplicate_line_for_same_account_and_period_rejected(): void
    {
        $lines = [
            ['account_id' => $this->expenseGl, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '100.0000'],
            ['account_id' => $this->expenseGl, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '200.0000'],
        ];

        $this->post('/budgets', $this->budgetPayload($lines))
            ->assertRedirect()
            ->assertSessionHas('error', 'Duplicate budget line for the same account and period.');

        $this->assertSame(0, Budget::query()->where('company_id', 1)->count());
    }

    // ───────────────────────── Lifecycle ─────────────────────────

    public function test_update_draft_budget(): void
    {
        $budget = $this->storeBudget();

        $this->get('/budgets/'.$budget->id.'/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Edit')
                ->where('budget.id', $budget->id)
                ->has('initialLines', 2));

        $this->put('/budgets/'.$budget->id, $this->budgetPayload([], ['name' => 'Renamed Budget']))
            ->assertRedirect();

        $budget->refresh();
        $this->assertSame('Renamed Budget', $budget->name);
        $this->assertSame(2, $budget->lines()->count());
    }

    public function test_delete_draft_budget(): void
    {
        $budget = $this->storeBudget();

        $this->delete('/budgets/'.$budget->id)->assertRedirect();

        $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
        $this->assertSame(0, BudgetLine::query()->where('budget_id', $budget->id)->count());
    }

    public function test_post_budget_marks_posted_and_locks_edits(): void
    {
        $budget = $this->storeBudget();

        $this->post('/budgets/'.$budget->id.'/post')->assertRedirect();

        $budget->refresh();
        $this->assertSame(BudgetStatus::Posted->value, $budget->status);

        $this->get('/budgets/'.$budget->id.'/edit')->assertRedirect();
        $this->put('/budgets/'.$budget->id, $this->budgetPayload())
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft budgets can be edited.');
        $this->delete('/budgets/'.$budget->id)
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft budgets can be edited.');

        $this->post('/budgets/'.$budget->id.'/post')
            ->assertRedirect()
            ->assertSessionHas('error', 'This budget is already posted.');
    }

    public function test_budget_with_zero_lines_cannot_be_posted(): void
    {
        $lines = [
            ['account_id' => $this->expenseGl, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '0'],
        ];

        $budget = $this->storeBudget(['lines' => $lines]);

        $this->assertSame(0, $budget->lines()->count());

        $this->post('/budgets/'.$budget->id.'/post')
            ->assertRedirect()
            ->assertSessionHas('error', 'A budget cannot be posted without budget lines.');
    }

    // ───────────────────────── Variance ─────────────────────────

    public function test_variance_reflects_posted_journal_actuals(): void
    {
        $lines = [
            ['account_id' => $this->expenseGl, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '10000.0000'],
        ];

        $budget = $this->storeBudget(['lines' => $lines]);

        $this->post('/journals', [
            'journal_date' => $this->openPeriod()->start_date->toDateString(),
            'period_id' => $this->openPeriod()->id,
            'description' => 'Budget test actual',
            'lines' => [
                ['account_id' => $this->expenseGl, 'description' => null, 'debit' => '5000.0000', 'credit' => '0'],
                ['account_id' => $this->cashGl, 'description' => null, 'debit' => '0', 'credit' => '5000.0000'],
            ],
        ])->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->latest('id')->firstOrFail();
        $this->post('/journals/'.$journal->id.'/post')->assertRedirect();
        $journal->refresh();
        $this->assertSame(TransactionStatus::Posted->value, $journal->status);

        $this->get('/budgets/'.$budget->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Show')
                ->has('variance.rows', 1)
                ->where('variance.rows.0.code', '5111')
                ->where('variance.rows.0.budgeted', 10000)
                ->where('variance.rows.0.actual', 5000)
                ->where('variance.rows.0.variance', 5000)
                ->where('variance.detail.0.actual_amount', 5000));
    }

    public function test_draft_journals_are_excluded_from_actuals(): void
    {
        $lines = [
            ['account_id' => $this->expenseGl, 'period_id' => $this->openPeriod()->id, 'budgeted_amount' => '10000.0000'],
        ];

        $budget = $this->storeBudget(['lines' => $lines]);

        $this->post('/journals', [
            'journal_date' => $this->openPeriod()->start_date->toDateString(),
            'period_id' => $this->openPeriod()->id,
            'description' => 'Draft actual',
            'lines' => [
                ['account_id' => $this->expenseGl, 'description' => null, 'debit' => '9000.0000', 'credit' => '0'],
                ['account_id' => $this->cashGl, 'description' => null, 'debit' => '0', 'credit' => '9000.0000'],
            ],
        ])->assertRedirect();

        $this->get('/budgets/'.$budget->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Show')
                ->where('variance.rows.0.actual', 0));
    }

    // ───────────────────────── Permissions ─────────────────────────

    public function test_accountant_can_manage_budgets(): void
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($user);

        $this->get('/budgets')->assertOk();
        $this->get('/budgets/create')->assertOk();

        $this->post('/budgets', $this->budgetPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $budget = Budget::query()->where('company_id', 1)->latest('id')->firstOrFail();

        $this->post('/budgets/'.$budget->id.'/post')->assertRedirect();
        $this->assertSame(BudgetStatus::Posted->value, $budget->fresh()->status);
    }

    public function test_viewer_is_readonly_for_budgets(): void
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        $this->actingAs($user);

        $this->get('/budgets')->assertOk();
        $this->get('/budgets/create')->assertForbidden();
        $this->post('/budgets', $this->budgetPayload())->assertForbidden();

        $budget = $this->adminCreateBudget();

        $this->actingAs($user);
        $this->get('/budgets/'.$budget->id)->assertOk();
        $this->post('/budgets/'.$budget->id.'/post')->assertForbidden();
    }

    protected function adminCreateBudget(): Budget
    {
        $this->actingAs($this->admin);

        return $this->storeBudget();
    }
}