<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase10bCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);
        session(['active_company_id' => 1]);
    }

    private function makeYear(string $start): FiscalYear
    {
        $start = Carbon::parse($start)->startOfYear();
        $end = $start->copy()->addYear()->subDay();
        $name = $start->format('Y').'-'.$end->format('Y');

        return FiscalYear::query()->create([
            'company_id' => 1,
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => false,
            'status' => 'open',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function makePeriods(FiscalYear $fy): void
    {
        $cursor = $fy->start_date->copy()->startOfMonth();
        $end = $fy->end_date;

        while ($cursor->lte($end)) {
            AccountingPeriod::query()->create([
                'fiscal_year_id' => $fy->id,
                'name' => $cursor->format('Y-m'),
                'start_date' => $cursor->copy(),
                'end_date' => $cursor->copy()->endOfMonth()->min($end),
                'is_active' => false,
                'status' => 'open',
            ]);

            $cursor->addMonth();
        }
    }

    private function closeAllPeriods(FiscalYear $fy): void
    {
        $fy->periods()->update(['status' => 'closed']);
    }

    private function postIncomeJournal(FiscalYear $fy): void
    {
        $period = $fy->periods()->orderBy('start_date')->first();
        $cash = Account::query()->where('company_id', 1)->where('code', '1111')->firstOrFail();
        $income = Account::query()->where('company_id', 1)->where('code', '4111')->firstOrFail();

        $this->post('/journals', [
            'journal_date' => $period->start_date->toDateString(),
            'period_id' => $period->id,
            'reference' => 'P10B',
            'description' => 'Phase 10b test',
            'lines' => [
                ['account_id' => $cash->id, 'description' => 'Dr', 'debit' => 1000.00, 'credit' => ''],
                ['account_id' => $income->id, 'description' => 'Cr', 'debit' => '', 'credit' => 1000.00],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $journal = Journal::query()->where('company_id', 1)->latest('id')->firstOrFail();
        $this->post(route('journals.post', $journal->id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_close_books_net_income_to_retained_earnings_and_carries_forward(): void
    {
        $fy = $this->makeYear('2040-01-01');
        $this->makePeriods($fy);
        $this->postIncomeJournal($fy);
        $this->closeAllPeriods($fy);

        // next year exists (open) so carry-forward should run
        $next = $this->makeYear('2041-01-01');
        $next->update(['is_active' => true]);
        $this->makePeriods($next);

        $this->post(route('fiscal-years.close', $fy->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('closed', $fy->fresh()->status);

        $closing = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'closing')
            ->where('source_id', $fy->id)
            ->firstOrFail();

        $this->assertSame('posted', $closing->status);
        $this->assertStringStartsWith('YEC-2040-', $closing->journal_no);
        $this->assertSame('2040-12-31', $closing->journal_date->toDateString());

        $re = Account::query()->where('company_id', 1)->where('code', '3211')->firstOrFail();
        $income = Account::query()->where('company_id', 1)->where('code', '4111')->firstOrFail();

        $incomeLine = $closing->lines()->where('account_id', $income->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $incomeLine->debit, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $incomeLine->credit, 0.0001);

        $reLine = $closing->lines()->where('account_id', $re->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $reLine->credit, 0.0001);

        // balanced
        $debits = (float) $closing->lines()->sum('debit');
        $credits = (float) $closing->lines()->sum('credit');
        $this->assertEqualsWithDelta($credits, $debits, 0.0001);

        // carry-forward opening journal in the next fiscal year
        $opening = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'opening')
            ->where('journal_date', '>=', $next->start_date)
            ->where('journal_date', '<=', $next->end_date)
            ->firstOrFail();

        $this->assertStringStartsWith('OB-2041-', $opening->journal_no);

        $cash = Account::query()->where('company_id', 1)->where('code', '1111')->firstOrFail();
        $cashLine = $opening->lines()->where('account_id', $cash->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $cashLine->debit, 0.0001);

        $reOpeningLine = $opening->lines()->where('account_id', $re->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $reOpeningLine->credit, 0.0001);
    }

    public function test_close_is_refused_while_periods_are_open(): void
    {
        $fy = $this->makeYear('2040-01-01');
        $this->makePeriods($fy);

        $this->post(route('fiscal-years.close', $fy->id))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Close all accounting periods'));

        $this->assertSame('open', $fy->fresh()->status);
    }

    public function test_close_is_refused_while_active(): void
    {
        $fy = $this->makeYear('2040-01-01');
        $this->makePeriods($fy);
        $this->closeAllPeriods($fy);
        $fy->update(['is_active' => true]);

        $this->post(route('fiscal-years.close', $fy->id))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Move the active flag'));

        $this->assertSame('open', $fy->fresh()->status);
    }

    public function test_reopen_is_blocked_when_a_later_year_is_closed(): void
    {
        $old = $this->makeYear('2040-01-01');
        $this->makePeriods($old);
        $this->closeAllPeriods($old);

        $next = $this->makeYear('2041-01-01');
        $this->makePeriods($next);
        $this->closeAllPeriods($next);

        $this->post(route('fiscal-years.close', $old->id))->assertRedirect();
        $this->post(route('fiscal-years.close', $next->id))->assertRedirect();

        $this->post(route('fiscal-years.reopen', $old->id))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'later fiscal year'));

        $this->assertSame('closed', $old->fresh()->status);
    }

    public function test_reopen_works_when_no_later_year_is_closed(): void
    {
        $old = $this->makeYear('2040-01-01');
        $this->makePeriods($old);
        $this->closeAllPeriods($old);

        $this->post(route('fiscal-years.close', $old->id))->assertRedirect();

        $this->post(route('fiscal-years.reopen', $old->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('open', $old->fresh()->status);
    }

    public function test_accountant_with_close_permission_can_close_but_viewer_cannot(): void
    {
        $fy = $this->makeYear('2040-01-01');
        $this->makePeriods($fy);
        $this->closeAllPeriods($fy);

        $accountant = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $accountant->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $accountant->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($accountant)
            ->post(route('fiscal-years.close', $fy->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('closed', $fy->fresh()->status);

        $open = $this->makeYear('2041-01-01');
        $this->makePeriods($open);

        $viewer = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $viewer->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $viewer->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        $this->actingAs($viewer)
            ->post(route('fiscal-years.close', $open->id))
            ->assertForbidden();
    }
}