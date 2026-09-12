<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\BankStatementImport;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\CashBank\Models\CashBankTransaction;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Phase6bCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $cashGl;

    private int $bankGl;

    private int $incomeGl;

    private int $expenseGl;

    private CashAccount $cashAccount;

    private BankAccount $bankAccount;

    private BankAccount $bankAccountB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->cashGl = $setting->default_cash_account_id;
        $this->bankGl = $setting->default_bank_account_id;
        $this->incomeGl = $setting->default_sales_account_id;
        $this->expenseGl = $setting->default_purchase_account_id;

        $this->cashAccount = CashAccount::query()->create([
            'company_id' => 1,
            'name' => 'Main Cash Register',
            'gl_account_id' => $this->cashGl,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'company_id' => 1,
            'account_name' => 'DBBL Current',
            'account_no' => '123-456',
            'bank_name' => 'Dutch Bangla Bank',
            'gl_account_id' => $this->bankGl,
            'is_active' => true,
        ]);

        $this->bankAccountB = BankAccount::query()->create([
            'company_id' => 1,
            'account_name' => 'Islami Bank A/C',
            'account_no' => '789-012',
            'bank_name' => 'Islami Bank',
            'gl_account_id' => $this->bankGl,
            'is_active' => true,
        ]);
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_cash_bank_pages_render_for_super_admin(): void
    {
        $this->get('/cash-bank')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CashBank/Index')
                ->has('cashAccounts', 1)
                ->has('bankAccounts', 2)
                ->has('totalCash')
                ->has('totalBank'));

        $this->get('/cash-bank/transactions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CashBank/Transactions')
                ->has('transactions.data')
                ->has('counterAccounts'));

        $this->get('/cash-bank/accounts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CashBank/Accounts')
                ->has('glAccounts')
                ->has('currencies'));

        $this->get('/cash-bank/reconciliations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('CashBank/Reconciliations'));
    }

    // ───────────────────────── Accounts ─────────────────────────

    public function test_cash_and_bank_accounts_can_be_created_via_forms(): void
    {
        $this->post(route('cash-bank.accounts.store-cash'), [
            'name' => 'Toll Booth Cash',
            'gl_account_id' => $this->cashGl,
            'is_active' => 1,
        ])->assertRedirect(route('cash-bank.accounts'));

        $this->assertSame(2, CashAccount::query()->where('company_id', 1)->count());

        $this->post(route('cash-bank.accounts.store-bank'), [
            'account_name' => 'City Bank A/C',
            'account_no' => '555-777',
            'bank_name' => 'City Bank',
            'branch_name' => 'Gulshan',
            'gl_account_id' => $this->bankGl,
            'is_active' => 1,
        ])->assertRedirect(route('cash-bank.accounts'));

        $this->assertSame(3, BankAccount::query()->where('company_id', 1)->count());

        $created = BankAccount::query()->where('account_no', '555-777')->firstOrFail();
        $this->assertSame('Gulshan', $created->branch_name);

        $this->put(route('cash-bank.accounts.update-bank', $created->id), [
            'account_name' => 'City Bank A/C',
            'account_no' => '555-777',
            'bank_name' => 'City Bank',
            'branch_name' => 'Dhanmondi',
            'gl_account_id' => $this->bankGl,
            'is_active' => 1,
        ])->assertRedirect(route('cash-bank.accounts'));

        $this->assertSame('Dhanmondi', $created->fresh()->branch_name);

        $this->delete(route('cash-bank.accounts.destroy-bank', $created->id))->assertRedirect();
        $this->assertNotNull($created->fresh()->deleted_at);
    }

    public function test_account_with_transactions_cannot_be_deleted(): void
    {
        $this->postTransaction([
            'transaction_type' => 'cash_receipt',
            'cash_account_id' => $this->cashAccount->id,
            'counter_account_id' => $this->incomeGl,
        ])->assertRedirect();

        $this->delete(route('cash-bank.accounts.destroy-cash', $this->cashAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($this->cashAccount->fresh()->deleted_at);
    }

    // ───────────────────────── Transactions ─────────────────────────

    public function test_cash_receipt_posts_balanced_cbt_journal(): void
    {
        $this->postTransaction([
            'transaction_type' => 'cash_receipt',
            'cash_account_id' => $this->cashAccount->id,
            'counter_account_id' => $this->incomeGl,
            'reference' => 'SALE-2026',
        ])->assertRedirect();

        $transaction = CashBankTransaction::query()->where('company_id', 1)->firstOrFail();
        $this->assertSame('CBT-'.date('Y').'-0001', $transaction->transaction_no);

        $journal = Journal::query()->where('source_type', 'bank')->where('source_id', $transaction->id)->firstOrFail();
        $this->assertStringStartsWith('CBT-', $journal->journal_no);
        $this->assertSame('posted', $journal->status);
        $this->assertCount(2, $journal->lines);
        $this->assertSame($transaction->id, (int) $journal->source_id);

        $cashLine = $journal->lines->firstWhere('account_id', $this->cashGl);
        $counterLine = $journal->lines->firstWhere('account_id', $this->incomeGl);

        $this->assertEqualsWithDelta(500.0, (float) $cashLine->debit, 0.0001);
        $this->assertEqualsWithDelta(500.0, (float) $counterLine->credit, 0.0001);

        $this->assertEqualsWithDelta((float) $journal->totalDebit(), (float) $journal->totalCredit(), 0.0001);
    }

    public function test_every_transaction_type_posts_a_balanced_journal(): void
    {
        $cases = [
            'cash_payment' => [
                'cash_account_id' => $this->cashAccount->id,
                'counter_account_id' => $this->expenseGl,
                'dr' => $this->expenseGl,
                'cr' => $this->cashGl,
            ],
            'bank_deposit' => [
                'cash_account_id' => $this->cashAccount->id,
                'bank_account_id' => $this->bankAccount->id,
                'dr' => $this->bankGl,
                'cr' => $this->cashGl,
            ],
            'bank_withdrawal' => [
                'cash_account_id' => $this->cashAccount->id,
                'bank_account_id' => $this->bankAccount->id,
                'dr' => $this->cashGl,
                'cr' => $this->bankGl,
            ],
            'bank_transfer' => [
                'bank_account_id' => $this->bankAccount->id,
                'to_bank_account_id' => $this->bankAccountB->id,
                'dr' => $this->bankGl,
                'cr' => $this->bankGl,
            ],
            'bank_charge' => [
                'bank_account_id' => $this->bankAccount->id,
                'counter_account_id' => $this->expenseGl,
                'dr' => $this->expenseGl,
                'cr' => $this->bankGl,
            ],
            'bank_interest' => [
                'bank_account_id' => $this->bankAccount->id,
                'counter_account_id' => $this->incomeGl,
                'dr' => $this->bankGl,
                'cr' => $this->incomeGl,
            ],
        ];

        foreach ($cases as $type => $extra) {
            $this->postTransaction(array_merge(['transaction_type' => $type], $extra))->assertRedirect();

            $transaction = CashBankTransaction::query()->where('transaction_type', $type)->firstOrFail();
            $this->assertNotNull($transaction->journal_id);

            $journal = $transaction->journal;
            $this->assertSame('posted', $journal->status);
            $this->assertCount(2, $journal->lines);

            $debitLine = $journal->lines->firstWhere('debit', 500);
            $creditLine = $journal->lines->firstWhere('credit', 500);

            $this->assertNotNull($debitLine);
            $this->assertNotNull($creditLine);
            $this->assertSame($extra['dr'], (int) $debitLine->account_id);
            $this->assertSame($extra['cr'], (int) $creditLine->account_id);
            $this->assertEqualsWithDelta((float) $journal->totalDebit(), (float) $journal->totalCredit(), 0.0001);
        }
    }

    public function test_transaction_numbers_are_sequential_and_unique(): void
    {
        $this->postTransaction(['transaction_type' => 'bank_deposit', 'cash_account_id' => $this->cashAccount->id, 'bank_account_id' => $this->bankAccount->id])->assertRedirect();
        $this->postTransaction(['transaction_type' => 'bank_withdrawal', 'cash_account_id' => $this->cashAccount->id, 'bank_account_id' => $this->bankAccount->id])->assertRedirect();

        $numbers = CashBankTransaction::query()->pluck('transaction_no')->sort()->values();

        $this->assertSame(['CBT-'.date('Y').'-0001', 'CBT-'.date('Y').'-0002'], $numbers->all());
    }

    public function test_transfer_to_the_same_account_is_rejected(): void
    {
        $this->postTransaction([
            'transaction_type' => 'bank_transfer',
            'bank_account_id' => $this->bankAccount->id,
            'to_bank_account_id' => $this->bankAccount->id,
        ])->assertRedirect()->assertSessionHasErrors('to_bank_account_id');

        $this->assertSame(0, CashBankTransaction::query()->count());
    }

    public function test_inactive_bank_account_cannot_be_used(): void
    {
        $this->bankAccount->update(['is_active' => false]);

        $this->postTransaction([
            'transaction_type' => 'bank_deposit',
            'cash_account_id' => $this->cashAccount->id,
            'bank_account_id' => $this->bankAccount->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, CashBankTransaction::query()->count());
    }

    public function test_deleting_a_transaction_removes_its_journal(): void
    {
        $this->postTransaction(['transaction_type' => 'cash_receipt', 'cash_account_id' => $this->cashAccount->id, 'counter_account_id' => $this->incomeGl])->assertRedirect();

        $transaction = CashBankTransaction::query()->firstOrFail();

        $this->delete(route('cash-bank.transactions.destroy', $transaction->id))->assertRedirect();

        $this->assertNotNull($transaction->fresh()->deleted_at);
        $this->assertNotNull(Journal::query()->withTrashed()->find($transaction->journal_id)->deleted_at);
    }

    // ───────────────────────── Reconciliation ─────────────────────────

    public function test_statement_import_auto_matches_and_completes(): void
    {
        $this->postTransaction([
            'transaction_type' => 'bank_withdrawal',
            'cash_account_id' => $this->cashAccount->id,
            'bank_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-09-10',
            'amount' => 500.00,
        ])->assertRedirect();

        $this->post(route('cash-bank.reconciliations.store'), [
            'bank_account_id' => $this->bankAccount->id,
            'statement_month' => '2026-09',
            'statement_file' => UploadedFile::fake()->createWithContent('statement.csv', "date,description,amount\n2026-09-10,\"ATM Withdrawal\",500\n"),
        ])->assertRedirect();

        $import = BankStatementImport::query()->where('company_id', 1)->firstOrFail();
        $this->assertSame('in_progress', $import->status);
        $this->assertSame(1, $import->totalCount());
        $this->assertSame(1, $import->matchedCount());

        $this->post(route('cash-bank.reconciliations.complete', $import->id))->assertRedirect(route('cash-bank.reconciliations'));

        $import = $import->fresh();
        $this->assertSame('completed', $import->status);
        $this->assertNotNull($import->completed_at);

        $this->assertSame('2026-09-30', $this->bankAccount->fresh()->last_reconciled_date->toDateString());
    }

    public function test_completion_is_refused_while_lines_remain_unmatched(): void
    {
        $this->post(route('cash-bank.reconciliations.store'), [
            'bank_account_id' => $this->bankAccount->id,
            'statement_month' => '2026-09',
            'statement_file' => UploadedFile::fake()->createWithContent('statement.csv', "date,description,amount\n2026-09-08,\"Unknown Charge\",1250\n"),
        ])->assertRedirect();

        $import = BankStatementImport::query()->firstOrFail();
        $this->assertSame(1, $import->totalCount());
        $this->assertSame(0, $import->matchedCount());

        $this->post(route('cash-bank.reconciliations.complete', $import->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('in_progress', $import->fresh()->status);
        $this->assertNull($this->bankAccount->fresh()->last_reconciled_date);
    }

    public function test_locked_period_blocks_deletion_for_non_super_admin(): void
    {
        $this->postTransaction([
            'transaction_type' => 'bank_withdrawal',
            'cash_account_id' => $this->cashAccount->id,
            'bank_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-09-10',
            'amount' => 500.00,
        ])->assertRedirect();

        $this->post(route('cash-bank.reconciliations.store'), [
            'bank_account_id' => $this->bankAccount->id,
            'statement_month' => '2026-09',
            'statement_file' => UploadedFile::fake()->createWithContent('statement.csv', "date,description,amount\n2026-09-10,\"ATM Withdrawal\",500\n"),
        ])->assertRedirect();

        $import = BankStatementImport::query()->firstOrFail();
        $this->post(route('cash-bank.reconciliations.complete', $import->id))->assertRedirect();

        $this->postTransaction([
            'transaction_type' => 'bank_withdrawal',
            'cash_account_id' => $this->cashAccount->id,
            'bank_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-09-11',
            'amount' => 250.00,
        ])->assertRedirect();

        $transaction = CashBankTransaction::query()->where('amount', 250.00)->firstOrFail();

        $accountant = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $accountant->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $accountant->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($accountant)
            ->delete(route('cash-bank.transactions.destroy', $transaction->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($transaction->fresh()->deleted_at);
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_user_without_bank_permission_is_blocked(): void
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);

        $this->actingAs($user)->get('/cash-bank')->assertForbidden();
    }

    public function test_accountant_can_view_cash_bank(): void
    {
        $accountant = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $accountant->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $accountant->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($accountant)->get('/cash-bank')->assertOk();
        $this->actingAs($accountant)->get('/cash-bank/transactions')->assertOk();
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function postTransaction(array $overrides = [])
    {
        $payload = array_merge([
            'transaction_type' => 'cash_receipt',
            'transaction_date' => now()->toDateString(),
            'amount' => 500.00,
            'cash_account_id' => null,
            'bank_account_id' => null,
            'to_bank_account_id' => null,
            'counter_account_id' => null,
            'reference' => null,
            'description' => null,
        ], $overrides);

        return $this->post(route('cash-bank.transactions.store'), $payload);
    }
}