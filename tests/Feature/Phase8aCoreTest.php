<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8aCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $expenseGl;

    private int $cashGl;

    private int $apGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->cashGl = $setting->default_cash_account_id;
        $this->apGl = $setting->default_ap_account_id;

        $this->expenseGl = \App\Domain\Accounting\Models\Account::query()
            ->where('company_id', 1)
            ->where('code', '5111')
            ->value('id');
    }

    protected function openPeriod(): AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('status', 'open')
            ->orderBy('start_date')
            ->firstOrFail();
    }

    protected function periodsOfFiscalYear(): array
    {
        return AccountingPeriod::query()
            ->where('fiscal_year_id', $this->openPeriod()->fiscal_year_id)
            ->orderBy('start_date')
            ->get()
            ->all();
    }

    protected function postManualJournal(string $date, int $periodId, float $amount, string $tag): Journal
    {
        $this->post('/journals', [
            'journal_date' => $date,
            'period_id' => $periodId,
            'description' => $tag,
            'lines' => [
                ['account_id' => $this->expenseGl, 'description' => null, 'debit' => number_format($amount, 4, '.', ''), 'credit' => '0'],
                ['account_id' => $this->cashGl, 'description' => null, 'debit' => '0', 'credit' => number_format($amount, 4, '.', '')],
            ],
        ])->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->where('description', $tag)->firstOrFail();
        $this->post('/journals/'.$journal->id.'/post')->assertRedirect();

        return $journal->fresh();
    }

    protected function reportProps(string $url): array
    {
        $response = $this->get($url);

        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_reports_pages_render_for_super_admin(): void
    {
        $this->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Report/Index')
                ->where('report', 'general-ledger')
                ->has('filters.fiscalYears')
                ->has('filters.periods')
                ->has('accounts')
                ->has('entries')
                ->has('summary')
                ->has('totals.debit'));

        $this->get('/reports?report=trial-balance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Report/Index')
                ->where('report', 'trial-balance')
                ->has('rows')
                ->has('totals.debit')
                ->has('balanced'));
    }

    public function test_trial_balance_is_balanced_and_lists_normal_sides(): void
    {
        $period = $this->openPeriod();
        $this->postManualJournal($period->start_date->toDateString(), $period->id, 1000.0, 'TB Test Journal');

        $props = $this->reportProps('/reports?report=trial-balance&fiscal_year_id='.$period->fiscal_year_id.'&period_id='.$period->id);

        $this->assertTrue($props['balanced']);
        $this->assertSame($props['totals']['debit'], $props['totals']['credit']);

        $expenseRow = collect($props['rows'])->firstWhere('code', '5111');
        $this->assertNotNull($expenseRow);
        $this->assertSame(1000.0, $expenseRow['debit']);
        $this->assertSame(0.0, $expenseRow['credit']);

        $cashRow = collect($props['rows'])->firstWhere('account_id', $this->cashGl);
        $this->assertNotNull($cashRow);
        $this->assertSame(-1000.0, $cashRow['debit']);
    }

    // ───────────────────────── General Ledger ─────────────────────────

    public function test_gl_running_balance_carries_prior_period(): void
    {
        $periods = $this->periodsOfFiscalYear();
        $this->assertCount(2, array_slice($periods, 0, 2));
        [$first, $second] = array_slice($periods, 0, 2);

        $this->postManualJournal($first->start_date->toDateString(), $first->id, 6000.0, 'GL Prior Journal');
        $this->postManualJournal($second->start_date->toDateString(), $second->id, 4000.0, 'GL Current Journal');

        $props = $this->reportProps('/reports?fiscal_year_id='.$first->fiscal_year_id.'&period_id='.$second->id.'&account_id='.$this->expenseGl);

        $expense = collect($props['entries'])->filter(fn ($e) => $e['account_id'] === $this->expenseGl)->values();

        $this->assertCount(1, $expense);
        $this->assertSame(4000.0, $expense[0]['debit']);
        $this->assertSame(10000.0, $expense[0]['running_balance']);
    }

    public function test_gl_summary_and_totals_over_multiple_accounts(): void
    {
        $first = $this->periodsOfFiscalYear()[0];
        $this->postManualJournal($first->start_date->toDateString(), $first->id, 2500.0, 'GL Summary Journal');

        $props = $this->reportProps('/reports?fiscal_year_id='.$first->fiscal_year_id.'&period_id='.$first->id);

        $this->assertCount(2, $props['summary']);

        $expense = collect($props['summary'])->firstWhere('account_code', '5111');
        $this->assertSame(2500.0, $expense['debit']);
        $this->assertSame(2500.0, $expense['closing_balance']);

        $this->assertSame(2500.0, $props['totals']['debit']);
        $this->assertSame(2500.0, $props['totals']['credit']);
        $this->assertCount(2, $props['entries']);
    }

    public function test_draft_journals_are_excluded_from_reports(): void
    {
        $period = $this->openPeriod();

        $this->post('/journals', [
            'journal_date' => $period->start_date->toDateString(),
            'period_id' => $period->id,
            'description' => 'Draft Only Journal',
            'lines' => [
                ['account_id' => $this->expenseGl, 'description' => null, 'debit' => '5000.0000', 'credit' => '0'],
                ['account_id' => $this->cashGl, 'description' => null, 'debit' => '0', 'credit' => '5000.0000'],
            ],
        ])->assertRedirect();

        $props = $this->reportProps('/reports?fiscal_year_id='.$period->fiscal_year_id.'&period_id='.$period->id);

        $this->assertTrue(collect($props['entries'])->every(fn ($e) => $e['description'] !== 'Draft Only Journal'));

        $tb = $this->reportProps('/reports?report=trial-balance&fiscal_year_id='.$period->fiscal_year_id.'&period_id='.$period->id);
        $expenseRow = collect($tb['rows'])->firstWhere('code', '5111');
        $this->assertTrue(($expenseRow['debit'] ?? 0) < 5000.0);
    }

    // ───────────────────────── Period filtering ─────────────────────────

    public function test_period_filter_scopes_trial_balance_rows(): void
    {
        $periods = $this->periodsOfFiscalYear();
        [$first, $second] = array_slice($periods, 0, 2);

        $this->postManualJournal($first->start_date->toDateString(), $first->id, 1000.0, 'TB First Period');
        $this->postManualJournal($second->start_date->toDateString(), $second->id, 1000.0, 'TB Second Period');

        $firstProps = $this->reportProps('/reports?report=trial-balance&fiscal_year_id='.$first->fiscal_year_id.'&period_id='.$first->id);
        $expenseFirst = collect($firstProps['rows'])->firstWhere('code', '5111');
        $this->assertSame(1000.0, $expenseFirst['debit']);

        $wholeYear = $this->reportProps('/reports?report=trial-balance&fiscal_year_id='.$first->fiscal_year_id);
        $expenseYear = collect($wholeYear['rows'])->firstWhere('code', '5111');
        $this->assertSame(2000.0, $expenseYear['debit']);
    }

    // ───────────────────────── Permissions ─────────────────────────

    public function test_accountant_and_viewer_can_view_reports(): void
    {
        foreach (['accountant', 'viewer'] as $slug) {
            $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
            $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
            $user->roles()->attach(Role::query()->where('slug', $slug)->value('id'), ['company_id' => 1]);

            $this->actingAs($user);

            $this->get('/reports')->assertOk()->assertInertia(fn ($page) => $page->component('Report/Index'));
            $this->get('/reports?report=trial-balance')->assertOk()->assertInertia(fn ($page) => $page->component('Report/Index'));
        }
    }
}