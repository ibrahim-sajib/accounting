<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8bCoreTest extends TestCase
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

    protected function periodsOfFiscalYear(): array
    {
        return AccountingPeriod::query()
            ->where('fiscal_year_id', $this->openPeriod()->fiscal_year_id)
            ->orderBy('start_date')
            ->get()
            ->all();
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

    protected function expenseJournal(string $date, int $periodId, float $amount, string $tag): Journal
    {
        return $this->postJournal($date, $periodId, [[$this->expenseGl, $amount, 0], [$this->cashGl, 0, $amount]], $tag);
    }

    protected function revenueJournal(string $date, int $periodId, float $amount, string $tag): Journal
    {
        return $this->postJournal($date, $periodId, [[$this->cashGl, $amount, 0], [$this->revenueGl, 0, $amount]], $tag);
    }

    protected function statementProps(string $url): array
    {
        $response = $this->get($url);

        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_statements_pages_render_for_super_admin(): void
    {
        $this->get('/statements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Statements/Index')
                ->where('statement', 'income')
                ->has('filters.fiscalYears')
                ->has('filters.periods')
                ->has('rows')
                ->has('totals.net_income'));

        foreach ([
            'balance-sheet' => ['sections.assets', 'sections.liabilities', 'sections.equity', 'totals.difference'],
            'cash-flow' => ['rows', 'opening', 'closing', 'net_change', 'reconciled'],
            'equity' => ['rows', 'totals.opening', 'totals.closing'],
        ] as $statement => $keys) {
            $this->get('/statements?statement='.$statement)
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Statements/Index')
                    ->where('statement', $statement)
                    ->has('filters.fiscalYears')
                    ->has($keys[0]));
        }
    }

    // ───────────────────────── Income statement ─────────────────────────

    public function test_income_statement_reports_revenue_and_expenses_and_net_income(): void
    {
        $period = $this->openPeriod();
        $date = $period->start_date->toDateString();

        $this->revenueJournal($date, $period->id, 1500.0, 'IS Revenue');
        $this->expenseJournal($date, $period->id, 1000.0, 'IS Expense');

        $props = $this->statementProps('/statements?fiscal_year_id='.$period->fiscal_year_id.'&period_id='.$period->id);

        $revenue = collect($props['rows'])->firstWhere('name', 'Product Sales');
        $expense = collect($props['rows'])->firstWhere('name', 'Salaries & Wages');

        $this->assertSame(1500.0, $revenue['current']);
        $this->assertSame(1000.0, $expense['current']);
        $this->assertSame(1500.0, $props['totals']['income']);
        $this->assertSame(1000.0, $props['totals']['expense']);
        $this->assertSame(500.0, $props['totals']['net_income']);
        $this->assertSame(500.0, $props['totals']['net_income_ytd']);
    }

    public function test_period_filter_scopes_income_statement_current_vs_ytd(): void
    {
        [$first, $second] = array_slice($this->periodsOfFiscalYear(), 0, 2);

        $this->expenseJournal($first->start_date->toDateString(), $first->id, 1000.0, 'IS P1 Expense');
        $this->revenueJournal($second->start_date->toDateString(), $second->id, 1500.0, 'IS P2 Revenue');

        $periodProps = $this->statementProps('/statements?fiscal_year_id='.$first->fiscal_year_id.'&period_id='.$first->id);
        $this->assertSame(0.0, $periodProps['totals']['income']);
        $this->assertSame(1000.0, $periodProps['totals']['expense']);
        $this->assertSame(-1000.0, $periodProps['totals']['net_income']);
        $this->assertSame(-1000.0, $periodProps['totals']['net_income_ytd']);

        $yearProps = $this->statementProps('/statements?fiscal_year_id='.$first->fiscal_year_id);
        $this->assertSame(1500.0, $yearProps['totals']['income']);
        $this->assertSame(1000.0, $yearProps['totals']['expense']);
        $this->assertSame(500.0, $yearProps['totals']['net_income']);
    }

    // ───────────────────────── Balance sheet ─────────────────────────

    public function test_balance_sheet_rolls_tree_up_and_balances_with_equity(): void
    {
        $period = $this->openPeriod();
        $this->expenseJournal($period->start_date->toDateString(), $period->id, 1000.0, 'BS Expense');

        $props = $this->statementProps('/statements?statement=balance-sheet&fiscal_year_id='.$period->fiscal_year_id.'&period_id='.$period->id);

        $this->assertSame(0.0, $props['totals']['difference']);

        $assetCashGroup = collect($props['sections']['assets'])->firstWhere('name', 'Cash & Bank');
        $this->assertNotNull($assetCashGroup);
        $this->assertTrue($assetCashGroup['is_group']);
        $cashLeafTotal = collect($props['sections']['assets'])
            ->filter(fn ($r) => ! $r['is_group'] && $r['name'] === $this->cashAccountName())
            ->sum('current');
        $this->assertSame(round($cashLeafTotal, 4), $assetCashGroup['current']);

        $earningsRow = collect($props['sections']['equity'])->firstWhere('name', 'Current Year Earnings');
        $this->assertSame(-1000.0, $earningsRow['current']);

        $this->assertSame($props['totals']['assets'], $props['totals']['liabilities_equity']);
    }

    protected function cashAccountName(): string
    {
        return (string) Account::query()->where('company_id', 1)->where('id', $this->cashGl)->value('name');
    }

    // ───────────────────────── Cash flow ─────────────────────────

    public function test_cash_flow_classifies_and_reconciles(): void
    {
        [$first, $second] = array_slice($this->periodsOfFiscalYear(), 0, 2);

        $this->expenseJournal($first->start_date->toDateString(), $first->id, 1000.0, 'CF Expense');
        $this->revenueJournal($second->start_date->toDateString(), $second->id, 1500.0, 'CF Revenue');

        $props = $this->statementProps('/statements?statement=cash-flow&fiscal_year_id='.$first->fiscal_year_id);

        $this->assertSame(0.0, $props['opening']);
        $this->assertSame(500.0, $props['net_change']);
        $this->assertSame(500.0, $props['closing']);
        $this->assertTrue($props['reconciled']);

        $operating = collect($props['rows'])->firstWhere('key', 'operating');
        $this->assertSame(500.0, $operating['amount']);
        $this->assertSame(0.0, collect($props['rows'])->firstWhere('key', 'investing')['amount']);
        $this->assertSame(0.0, collect($props['rows'])->firstWhere('key', 'financing')['amount']);
    }

    // ───────────────────────── Statement of equity ─────────────────────────

    public function test_equity_statement_sums_current_year_earnings(): void
    {
        $period = $this->openPeriod();
        $this->expenseJournal($period->start_date->toDateString(), $period->id, 1000.0, 'EQ Expense');

        $props = $this->statementProps('/statements?statement=equity&fiscal_year_id='.$period->fiscal_year_id.'&period_id='.$period->id);

        $earnings = collect($props['rows'])->firstWhere('name', 'Current Year Earnings');
        $this->assertTrue($earnings['synthetic']);
        $this->assertSame(0.0, $earnings['opening']);
        $this->assertSame(-1000.0, $earnings['movement']);
        $this->assertSame(-1000.0, $earnings['closing']);

        $this->assertSame(0.0, $props['totals']['opening']);
        $this->assertSame(-1000.0, $props['totals']['movement']);
        $this->assertSame(-1000.0, $props['totals']['closing']);
    }

    // ───────────────────────── Permissions ─────────────────────────

    public function test_accountant_and_viewer_can_view_all_statements(): void
    {
        foreach (['accountant', 'viewer'] as $slug) {
            $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
            $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
            $user->roles()->attach(Role::query()->where('slug', $slug)->value('id'), ['company_id' => 1]);

            $this->actingAs($user);

            $this->get('/statements')->assertOk()->assertInertia(fn ($page) => $page->component('Statements/Index'));
            $this->get('/statements?statement=balance-sheet')->assertOk();
            $this->get('/statements?statement=cash-flow')->assertOk();
            $this->get('/statements?statement=equity')->assertOk();
        }
    }
}