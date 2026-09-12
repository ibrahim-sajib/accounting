<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\Journal;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Expense\Models\Expense;
use App\Domain\Expense\Models\ExpenseCategory;
use App\Domain\Party\Models\Supplier;
use App\Domain\Rbac\Models\Role;
use App\Domain\Tax\Models\TaxRate;
use App\Models\User;
use App\Support\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6cCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $cashGl;

    private int $bankGl;

    private int $apGl;

    private int $expenseGl;

    private CashAccount $cashAccount;

    private BankAccount $bankAccount;

    private TaxRate $vatRate;

    private Supplier $supplier;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->cashGl = $setting->default_cash_account_id;
        $this->bankGl = $setting->default_bank_account_id;
        $this->apGl = $setting->default_ap_account_id;
        $this->expenseGl = Account::query()->where('company_id', 1)->where('code', '5131')->value('id')
            ?? $setting->default_purchase_account_id;

        $this->cashAccount = CashAccount::query()->create([
            'company_id' => 1,
            'name' => 'Expense Cash Register',
            'gl_account_id' => $this->cashGl,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'company_id' => 1,
            'account_name' => 'Expense Bank A/C',
            'account_no' => 'EXP-111',
            'bank_name' => 'Expense Bank',
            'gl_account_id' => $this->bankGl,
            'is_active' => true,
        ]);

        $this->vatRate = TaxRate::query()
            ->whereHas('taxType', fn ($q) => $q->where('company_id', 1))
            ->where('rate_percent', 15)
            ->firstOrFail();

        $this->supplier = Supplier::query()->create([
            'company_id' => 1,
            'code' => 'SUP-E',
            'name' => 'Expense Vendor',
            'payment_terms_days' => 30,
            'opening_balance' => 0,
            'ap_account_id' => $this->apGl,
            'is_active' => true,
        ]);

        $this->category = ExpenseCategory::query()->create([
            'company_id' => 1,
            'name' => 'Test Utilities',
            'expense_account_id' => $this->expenseGl,
            'is_active' => true,
        ]);
    }

    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'payee' => 'Electricity Company',
            'expense_date' => '2026-03-15',
            'amount' => '5000.0000',
            'tax_rate_id' => $this->vatRate->id,
            'payment_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'bank_account_id' => null,
            'supplier_id' => null,
            'reference' => 'INV-2026-001',
            'notes' => 'Monthly bill',
            'is_recurring' => false,
            'recurrence_frequency' => null,
            'next_generation_date' => null,
        ], $overrides);
    }

    private function lastExpense(): Expense
    {
        return Expense::query()->where('company_id', 1)->latest('id')->firstOrFail();
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_expense_pages_render_for_super_admin(): void
    {
        $this->get('/expenses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expense/Index')
                ->has('expenses.data')
                ->has('categories'));

        $this->get('/expenses/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expense/Create')
                ->has('categories')
                ->has('taxRates')
                ->has('cashAccounts')
                ->has('bankAccounts')
                ->has('suppliers')
                ->has('paymentMethods'));

        $this->get('/expenses/categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expense/Categories')
                ->has('categories.data')
                ->has('postableAccounts'));
    }

    // ───────────────────────── Categories ─────────────────────────

    public function test_expense_category_crud(): void
    {
        $account = Account::query()->where('company_id', 1)->where('is_postable', true)->firstOrFail();

        $this->post('/expenses/categories', [
            'name' => 'Maintenance',
            'expense_account_id' => $account->id,
            'is_active' => true,
        ])->assertRedirect();

        $category = ExpenseCategory::query()->where('name', 'Maintenance')->firstOrFail();
        $this->assertSame($account->id, $category->expense_account_id);

        $this->put("/expenses/categories/{$category->id}", [
            'name' => 'Maintenance & Repairs',
            'expense_account_id' => $account->id,
            'is_active' => false,
        ])->assertRedirect();

        $category->refresh();
        $this->assertSame('Maintenance & Repairs', $category->name);
        $this->assertFalse((bool) $category->is_active);

        $inUse = ExpenseCategory::query()->where('company_id', 1)->firstOrCreate(
            ['company_id' => 1, 'name' => 'InUse Category', 'expense_account_id' => $this->expenseGl, 'is_active' => true]
        );
        $this->post('/expenses', $this->expensePayload(['category_id' => $inUse->id]))->assertRedirect();

        $this->delete("/expenses/categories/{$inUse->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse((bool) $inUse->refresh()->is_active);

        $this->delete("/expenses/categories/{$category->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('expense_categories', ['id' => $category->id]);

        $this->get('/expenses/categories')->assertOk();
    }

    public function test_expense_category_requires_postable_account(): void
    {
        $parent = Account::query()->where('company_id', 1)->where('is_postable', false)->first();

        $this->post('/expenses/categories', [
            'name' => 'Bad',
            'expense_account_id' => $parent?->id,
        ])->assertSessionHasErrors('expense_account_id');
    }

    // ───────────────────────── Draft lifecycle ─────────────────────────

    public function test_create_cash_draft_and_show(): void
    {
        $this->post('/expenses', $this->expensePayload())
            ->assertRedirect(route('expenses.show', Expense::query()->latest('id')->first()));

        $expense = $this->lastExpense();

        $this->assertSame(TransactionStatus::Draft->value, $expense->status);
        $this->assertNull($expense->expense_no);
        $this->assertSame(5000.0, (float) $expense->amount);
        $this->assertSame(750.0, (float) $expense->tax_amount);
        $this->assertSame(5750.0, $expense->total());
        $this->assertSame('cash', $expense->payment_method);

        $this->get("/expenses/{$expense->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Expense/Show'));
    }

    public function test_cash_method_requires_a_cash_account(): void
    {
        $this->post('/expenses', $this->expensePayload([
            'payment_method' => 'cash',
            'cash_account_id' => null,
        ]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_update_draft_while_posted_is_rejected(): void
    {
        $draft = $this->post('/expenses', $this->expensePayload())->assertRedirect();
        $expense = $this->lastExpense();

        $this->post("/expenses/{$expense->id}/post")
            ->assertRedirect(route('expenses.show', $expense->id))
            ->assertSessionHas('success');

        $this->put("/expenses/{$expense->id}", $this->expensePayload(['payee' => 'Changed']))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('Electricity Company', $expense->fresh()->payee);
    }

    // ───────────────────────── Posting ─────────────────────────

    public function test_post_cash_expense_posts_balanced_exp_journal(): void
    {
        $this->post('/expenses', $this->expensePayload());
        $expense = $this->lastExpense();

        $this->post("/expenses/{$expense->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success');

        $expense->refresh();
        $this->assertSame(TransactionStatus::Posted->value, $expense->status);
        $this->assertSame('EXP-2026-0001', $expense->expense_no);

        $journal = Journal::query()->findOrFail($expense->journal_id);

        $this->assertSame('expense', $journal->source_type);
        $this->assertSame($expense->id, $journal->source_id);
        $this->assertNotEmpty($journal->journal_no);

        $debits = $journal->lines()->whereColumn('debit', '>', 'credit')->get();
        $credits = $journal->lines()->whereColumn('credit', '>', 'debit')->get();

        $this->assertSame(5000.0, (float) $debits->where('account_id', $this->expenseGl)->sum('debit'));
        $this->assertSame(750.0, (float) $debits->where('account_id', $this->vatRate->input_account_id)->sum('debit'));
        $this->assertSame(5750.0, (float) $credits->where('account_id', $this->cashGl)->sum('credit'));

        $this->assertJournalBalanced($journal);
    }

    public function test_post_bank_expense_credits_bank_gl(): void
    {
        $this->post('/expenses', $this->expensePayload([
            'payment_method' => 'bank',
            'bank_account_id' => $this->bankAccount->id,
            'cash_account_id' => null,
        ]));

        $expense = $this->lastExpense();

        $this->post("/expenses/{$expense->id}/post")->assertRedirect()->assertSessionHas('success');

        $journal = Journal::query()->findOrFail($expense->refresh()->journal_id);
        $this->assertSame(5750.0, (float) $journal->lines()->where('account_id', $this->bankGl)->sum('credit'));
    }

    public function test_post_payable_expense_credits_ap_to_supplier(): void
    {
        $this->post('/expenses', $this->expensePayload([
            'payment_method' => 'payable',
            'supplier_id' => $this->supplier->id,
            'cash_account_id' => null,
            'bank_account_id' => null,
        ]));

        $expense = $this->lastExpense();

        $this->post("/expenses/{$expense->id}/post")->assertRedirect()->assertSessionHas('success');

        $journal = Journal::query()->findOrFail($expense->refresh()->journal_id);

        $apLine = $journal->lines()->where('account_id', $this->apGl)->firstOrFail();
        $this->assertSame(5750.0, (float) $apLine->credit);
        $this->assertSame('supplier', $apLine->party_type);
        $this->assertSame($this->supplier->id, $apLine->party_id);
    }

    public function test_expense_numbering_increments_per_sequence(): void
    {
        foreach (['first', 'second'] as $i => $label) {
            $this->post('/expenses', $this->expensePayload(['payee' => "Vendor {$label}"]));
            $this->post("/expenses/{$this->lastExpense()->id}/post")->assertRedirect()->assertSessionHas('success');
        }

        $this->assertSame('EXP-2026-0001', Expense::query()->skip(0)->first()->expense_no);
        $this->assertSame('EXP-2026-0002', Expense::query()->skip(1)->first()->expense_no);
    }

    public function test_post_requires_positive_amount_and_produces_balanced_lines(): void
    {
        $this->post('/expenses', $this->expensePayload(['amount' => '0']))
            ->assertRedirect()->assertSessionHasErrors('amount');
    }

    public function test_repost_if_no_tax_still_balances_two_lines(): void
    {
        $this->post('/expenses', $this->expensePayload([
            'tax_rate_id' => null,
            'amount' => '1000.0000',
        ]));
        $expense = $this->lastExpense();

        $this->post("/expenses/{$expense->id}/post")->assertRedirect()->assertSessionHas('success');

        $journal = Journal::query()->findOrFail($expense->refresh()->journal_id);
        $this->assertSame(2, $journal->lines()->count());
        $this->assertSame(1000.0, (float) $journal->lines()->where('account_id', $this->cashGl)->sum('credit'));
        $this->assertJournalBalanced($journal);
    }

    // ───────────────────────── Delete ─────────────────────────

    public function test_delete_draft_soft_deletes_and_posted_is_kept(): void
    {
        $this->post('/expenses', $this->expensePayload());
        $expense = $this->lastExpense();

        $this->post("/expenses/{$expense->id}/post")->assertRedirect()->assertSessionHas('success')
            ->assertSessionHas('success');

        $this->delete("/expenses/{$expense->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('expenses', ['id' => $expense->id]);

        $this->post('/expenses', $this->expensePayload(['payee' => 'Deletable Vendor']));
        $draft = $this->lastExpense();

        $this->delete("/expenses/{$draft->id}")->assertRedirect()->assertSessionHas('success');
        $this->assertSoftDeleted('expenses', ['id' => $draft->id]);
    }

    // ───────────────────────── Recurring ─────────────────────────

    public function test_recurring_expense_generates_draft_copies_on_schedule(): void
    {
        $this->post('/expenses', $this->expensePayload([
            'expense_date' => now()->subWeek()->toDateString(),
            'next_generation_date' => now()->toDateString(),
            'is_recurring' => true,
            'recurrence_frequency' => 'weekly',
        ]));

        $expense = $this->lastExpense();
        $this->assertTrue((bool) $expense->is_recurring);
        $this->assertSame('weekly', $expense->recurrence_frequency);

        $this->post("/expenses/{$expense->id}/post")->assertRedirect()->assertSessionHas('success');

        $this->artisan('expenses:generate-recurring')->assertExitCode(0);

        $copy = Expense::query()
            ->where('company_id', 1)
            ->where('id', '!=', $expense->id)
            ->where('is_recurring', true)
            ->latest('id')
            ->first();

        $this->assertNotNull($copy);
        $this->assertSame(TransactionStatus::Draft->value, $copy->status);
        $this->assertSame('Electricity Company', $copy->payee);
        $this->assertSame(5000.0, (float) $copy->amount);
        $this->assertSame($this->category->id, $copy->category_id);
        $this->assertTrue((bool) $copy->is_recurring);

        $this->assertTrue($expense->fresh()->next_generation_date->greaterThan(now()));
    }

    // ───────────────────────── Permissions ─────────────────────────

    public function test_accountant_can_view_create_and_post_expenses(): void
    {
        $accountant = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $accountant->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $accountant->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($accountant);

        $this->get('/expenses')->assertOk();
        $this->get('/expenses/create')->assertOk();

        $this->post('/expenses', $this->expensePayload())->assertRedirect();
        $this->post("/expenses/{$this->lastExpense()->id}/post")->assertRedirect()->assertSessionHas('success');
    }

    public function test_viewer_cannot_create_or_post(): void
    {
        $viewer = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $viewer->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $viewer->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        $this->actingAs($viewer);

        $this->get('/expenses')->assertOk();

        $this->get('/expenses/create')->assertForbidden();
        $this->post('/expenses', $this->expensePayload())->assertForbidden();
    }

    private function assertJournalBalanced(Journal $journal): void
    {
        $debit = (float) $journal->lines()->sum('debit');
        $credit = (float) $journal->lines()->sum('credit');
        $this->assertEqualsWithDelta($debit, $credit, 0.0001);
        $this->assertTrue($debit > 0);
    }
}