<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Models\OpeningBalance;
use App\Models\User;
use App\Support\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $cashAccount;

    private Account $incomeAccount;

    private AccountingPeriod $openPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->cashAccount = Account::query()->where('company_id', 1)->where('code', '1111')->firstOrFail();
        $this->incomeAccount = Account::query()->where('company_id', 1)->where('code', '4111')->firstOrFail();

        $this->openPeriod = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1)->where('is_active', true))
            ->where('status', 'open')
            ->orderBy('start_date')
            ->firstOrFail();
    }

    // ───────────────────────── Journals: pages ─────────────────────────

    public function test_super_admin_can_view_journals_index(): void
    {
        $this->actingAs($this->admin)
            ->get('/journals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Journals/Index')
                ->has('journals.data')
                ->has('journals.total'));
    }

    public function test_journal_create_page_exposes_periods_and_postable_accounts(): void
    {
        $this->actingAs($this->admin)
            ->get('/journals/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Journals/Create')
                ->has('periods')
                ->has('accounts'));
    }

    // ───────────────────────── Journals: store / post / reverse / delete ─────────────────────────

    public function test_journal_draft_can_be_saved(): void
    {
        $this->actingAs($this->admin)->post('/journals', $this->journalPayload())
            ->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('draft', $journal->status);
        $this->assertNull($journal->journal_no);
        $this->assertCount(2, $journal->lines);
    }

    public function test_posted_journal_gets_number_and_balanced_lines(): void
    {
        $journal = $this->createPostedJournal();

        $this->assertSame(TransactionStatus::Posted->value, $journal->status);
        $this->assertNotNull($journal->journal_no);
        $this->assertStringStartsWith('GJ-', $journal->journal_no);
        $this->assertNotNull($journal->posted_at);
        $this->assertEqualsWithDelta(
            (float) $journal->totalDebit(),
            (float) $journal->totalCredit(),
            0.0001
        );
    }

    public function test_unbalanced_journal_cannot_be_posted(): void
    {
        $this->actingAs($this->admin)->post('/journals', $this->journalPayload([
            'lines' => [
                0 => ['debit' => 500.00, 'credit' => 400.00],
            ],
        ]))->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('journals.post', $journal->id))
            ->assertRedirect();

        $this->assertSame('draft', $journal->fresh()->status);
        $this->assertNull($journal->fresh()->journal_no);
    }

    public function test_posted_journal_can_be_viewed(): void
    {
        $journal = $this->createPostedJournal();

        $this->actingAs($this->admin)
            ->get(route('journals.show', $journal->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Journals/Show')
                ->has('journal.lines', 2));
    }

    public function test_only_draft_can_be_edited(): void
    {
        $journal = $this->createPostedJournal();

        $this->actingAs($this->admin)
            ->get(route('journals.edit', $journal->id))
            ->assertStatus(422);
    }

    public function test_draft_can_be_updated(): void
    {
        $this->actingAs($this->admin)->post('/journals', $this->journalPayload())->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->put(route('journals.update', $journal->id), $this->journalPayload([
            'description' => 'Updated memo',
        ]))->assertRedirect();

        $this->assertSame('Updated memo', $journal->fresh()->description);
        $this->assertSame('draft', $journal->fresh()->status);
    }

    public function test_posted_journal_can_be_reversed(): void
    {
        $journal = $this->createPostedJournal();

        $this->actingAs($this->admin)
            ->post(route('journals.reverse', $journal->id))
            ->assertRedirect();

        $this->assertSame('reversed', $journal->fresh()->status);

        $reversal = Journal::query()
            ->where('company_id', 1)
            ->where('reversed_journal_id', $journal->id)
            ->firstOrFail();

        $this->assertSame('posted', $reversal->status);
        $this->assertEqualsWithDelta((float) $journal->lines->sum('debit'), (float) $reversal->lines->sum('credit'), 0.0001);
        $this->assertEqualsWithDelta((float) $journal->lines->sum('credit'), (float) $reversal->lines->sum('debit'), 0.0001);
    }

    public function test_draft_journal_can_be_deleted(): void
    {
        $this->actingAs($this->admin)->post('/journals', $this->journalPayload())->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('journals.destroy', $journal->id))
            ->assertRedirect();

        $this->assertNotNull($journal->fresh()->deleted_at);
    }

    public function test_empty_amounts_normalize_to_zero_for_journal_lines(): void
    {
        $this->actingAs($this->admin)->post('/journals', $this->journalPayload([
            'lines' => [
                0 => ['debit' => '', 'credit' => '600.00'],
            ],
        ]))->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->firstOrFail();

        $this->assertEquals(0, (float) $journal->lines->first()->debit);
        $this->assertEquals(600.0, (float) $journal->lines->first()->credit);
    }

    // ───────────────────────── Opening balances ─────────────────────────

    public function test_super_admin_can_view_opening_balances_index(): void
    {
        $this->actingAs($this->admin)
            ->get('/opening-balances')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpeningBalances/Index')
                ->has('fiscalYears'));
    }

    public function test_opening_balance_entry_page_exposes_postable_accounts(): void
    {
        $fiscalYear = FiscalYear::query()->where('company_id', 1)->where('is_active', true)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('opening-balances.entry', $fiscalYear->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpeningBalances/Entry')
                ->has('accounts')
                ->where('posted', false));
    }

    public function test_opening_balance_draft_can_be_saved(): void
    {
        $fiscalYear = FiscalYear::query()->where('company_id', 1)->where('is_active', true)->firstOrFail();

        $this->actingAs($this->admin)->post(route('opening-balances.save', $fiscalYear->id), [
            'entries' => $this->obEntries(),
        ])->assertRedirect();

        $this->assertSame(2, OpeningBalance::query()->where('fiscal_year_id', $fiscalYear->id)->count());
        $this->assertSame('draft', OpeningBalance::query()->first()->status);
    }

    public function test_opening_balances_post_creates_ob_journal(): void
    {
        $fiscalYear = FiscalYear::query()->where('company_id', 1)->where('is_active', true)->firstOrFail();

        $this->actingAs($this->admin)->post(route('opening-balances.post', $fiscalYear->id), [
            'entries' => $this->obEntries(),
        ])->assertRedirect();

        $journal = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'opening')
            ->firstOrFail();

        $this->assertStringStartsWith('OB-', $journal->journal_no);
        $this->assertSame($fiscalYear->start_date->toDateString(), $journal->journal_date->toDateString());
        $this->assertSame(TransactionStatus::Posted->value, $journal->status);
        // Both accounts must appear as journal lines.
        $this->assertCount(2, $journal->lines);
        // All opening balance rows finalize as posted and reference the journal.
        $this->assertSame(2, OpeningBalance::query()->where('fiscal_year_id', $fiscalYear->id)->where('status', 'posted')->count());
        $this->assertSame($journal->id, OpeningBalance::query()->where('fiscal_year_id', $fiscalYear->id)->first()->journal_id);
    }

    public function test_opening_balances_cannot_be_posted_twice(): void
    {
        $fiscalYear = FiscalYear::query()->where('company_id', 1)->where('is_active', true)->firstOrFail();

        $this->actingAs($this->admin)->post(route('opening-balances.post', $fiscalYear->id), [
            'entries' => $this->obEntries(),
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('opening-balances.post', $fiscalYear->id), [
            'entries' => $this->obEntries(),
        ])->assertRedirect();

        $this->assertSame(1, Journal::query()->where('company_id', 1)->where('source_type', 'opening')->count());
    }

    public function test_unbalanced_opening_balances_are_rejected_on_post(): void
    {
        $fiscalYear = FiscalYear::query()->where('company_id', 1)->where('is_active', true)->firstOrFail();

        $this->actingAs($this->admin)->post(route('opening-balances.post', $fiscalYear->id), [
            'entries' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->incomeAccount->id, 'debit' => 0, 'credit' => 999],
            ],
        ])->assertRedirect();

        $this->assertSame(0, Journal::query()->where('source_type', 'opening')->count());
        $this->assertSame(0, OpeningBalance::query()->count());
    }

    public function test_balance_sheet_accounts_cannot_receive_opening_debit_and_credit(): void
    {
        $fiscalYear = FiscalYear::query()->where('company_id', 1)->where('is_active', true)->firstOrFail();

        $this->actingAs($this->admin)->post(route('opening-balances.post', $fiscalYear->id), [
            'entries' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 1000, 'credit' => 50],
                ['account_id' => $this->incomeAccount->id, 'debit' => 0, 'credit' => 950],
            ],
        ])->assertRedirect();

        $this->assertSame(0, Journal::query()->where('source_type', 'opening')->count());
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_non_super_admin_without_permission_is_blocked(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/journals')->assertForbidden();
        $this->actingAs($user)->get('/opening-balances')->assertForbidden();
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function journalPayload(array $overrides = []): array
    {
        $payload = [
            'journal_date' => $this->openPeriod->start_date->toDateString(),
            'period_id' => $this->openPeriod->id,
            'reference' => 'TEST-REF',
            'description' => 'Phase 4 test journal',
            'lines' => [
                [
                    'account_id' => $this->cashAccount->id,
                    'description' => 'Debit cash',
                    'debit' => 1000.00,
                    'credit' => '',
                ],
                [
                    'account_id' => $this->incomeAccount->id,
                    'description' => 'Credit income',
                    'debit' => '',
                    'credit' => 1000.00,
                ],
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    private function createPostedJournal(): Journal
    {
        $this->actingAs($this->admin)->post('/journals', $this->journalPayload())->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('journals.post', $journal->id))
            ->assertRedirect();

        return $journal->fresh();
    }

    private function obEntries(): array
    {
        return [
            ['account_id' => $this->cashAccount->id, 'debit' => 5000, 'credit' => 0],
            ['account_id' => $this->incomeAccount->id, 'debit' => 0, 'credit' => 5000],
        ];
    }
}