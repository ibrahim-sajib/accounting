<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8cCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $cashGl;

    private int $expenseGl;

    private int $revenueGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->cashGl = $setting->default_cash_account_id;

        $this->expenseGl = Account::query()->where('company_id', 1)->where('code', '5111')->value('id');
        $this->revenueGl = Account::query()->where('company_id', 1)->where('code', '4111')->value('id');
    }

    protected function openPeriod(): AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('status', 'open')
            ->orderBy('start_date')
            ->firstOrFail();
    }

    protected function activePeriod(): AccountingPeriod
    {
        $fy = \App\Domain\Accounting\Models\FiscalYear::query()
            ->where('company_id', 1)
            ->where('is_active', true)
            ->firstOrFail();

        return AccountingPeriod::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    protected function postJournal(string $date, int $periodId, array $lines, string $tag): Journal
    {
        $payload = array_map(fn ($l) => [
            'account_id' => $l[0],
            'description' => null,
            'debit' => number_format($l[1], 4, '.', ''),
            'credit' => number_format($l[2], 4, '.', ''),
        ], $lines);

        $this->post('/journals', [
            'journal_date' => $date,
            'period_id' => $periodId,
            'description' => $tag,
            'lines' => $payload,
        ])->assertRedirect();

        $journal = Journal::query()->where('company_id', 1)->where('description', $tag)->firstOrFail();
        $this->post('/journals/'.$journal->id.'/post')->assertRedirect();

        return $journal->fresh();
    }

    protected function dashboardProps(): array
    {
        $response = $this->get('/dashboard');

        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    // ───────────────────────── Render + shape ─────────────────────────

    public function test_dashboard_renders_with_metrics_shape(): void
    {
        $props = $this->dashboardProps();

        $this->assertArrayHasKey('summary', $props);
        $this->assertArrayHasKey('metrics', $props);

        $m = $props['metrics'];

        $this->assertIsArray($m['range']);
        $this->assertArrayHasKey('from', $m['range']);
        $this->assertArrayHasKey('to', $m['range']);
        $this->assertIsNumeric($m['cash_balance']);
        $this->assertCount(3, $m['receivables']);
        $this->assertCount(3, $m['payables']);
        $this->assertCount(3, $m['activity']);
        $this->assertCount(4, $m['trial_balance']);
        $this->assertIsArray($m['recent_journals']);
    }

    // ───────────────────────── Ledger KPIs ─────────────────────────

    public function test_dashboard_activity_and_trial_balance_reflect_posted_journals(): void
    {
        $period = $this->activePeriod();
        $date = $period->start_date->toDateString();

        $this->postJournal($date, $period->id, [[$this->cashGl, 1500, 0], [$this->revenueGl, 0, 1500]], 'Dash Revenue');
        $this->postJournal($date, $period->id, [[$this->expenseGl, 1000, 0], [$this->cashGl, 0, 1000]], 'Dash Expense');

        $m = $this->dashboardProps()['metrics'];

        $this->assertSame(500.0, $m['cash_balance']);
        $this->assertSame(1500.0, $m['activity']['income']);
        $this->assertSame(1000.0, $m['activity']['expense']);
        $this->assertSame(500.0, $m['activity']['net']);
        $this->assertTrue($m['trial_balance']['balanced']);
        $this->assertSame(0.0, $m['trial_balance']['difference']);
        $this->assertSame($m['trial_balance']['debit'], $m['trial_balance']['credit']);

        $recent = array_column($m['recent_journals'], 'description');
        $this->assertContains('Dash Revenue', $recent);
        $this->assertContains('Dash Expense', $recent);
    }

    public function test_draft_journals_are_excluded_from_dashboard_metrics(): void
    {
        $period = $this->openPeriod();

        $this->post('/journals', [
            'journal_date' => $period->start_date->toDateString(),
            'period_id' => $period->id,
            'description' => 'Dash Draft Only',
            'lines' => [
                ['account_id' => $this->expenseGl, 'description' => null, 'debit' => '5000.0000', 'credit' => '0'],
                ['account_id' => $this->cashGl, 'description' => null, 'debit' => '0', 'credit' => '5000.0000'],
            ],
        ])->assertRedirect();

        $m = $this->dashboardProps()['metrics'];

        $this->assertSame(0.0, $m['cash_balance']);
        $this->assertSame(0.0, $m['activity']['expense']);
        $this->assertTrue(collect($m['recent_journals'])->every(fn ($j) => $j['description'] !== 'Dash Draft Only'));
    }

    // ───────────────────────── AR / AP open item counts ─────────────────────────

    public function test_dashboard_tracks_open_invoices_and_bills(): void
    {
        $existing = $this->dashboardProps()['metrics'];

        $this->assertIsInt($existing['receivables']['open_invoices']);
        $this->assertIsInt($existing['payables']['open_bills']);
        $this->assertIsNumeric($existing['receivables']['balance']);
        $this->assertIsNumeric($existing['payables']['balance']);
        $this->assertIsNumeric($existing['receivables']['overdue']);
        $this->assertIsNumeric($existing['payables']['overdue']);
    }

    // ───────────────────────── Permissions ─────────────────────────

    public function test_any_active_company_user_can_view_dashboard(): void
    {
        $accountant = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $accountant->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $accountant->roles()->attach(\App\Domain\Rbac\Models\Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($accountant);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }
}