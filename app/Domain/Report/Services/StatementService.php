<?php

namespace App\Domain\Report\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\JournalLine;
use App\Support\Enums\AccountType;
use Illuminate\Support\Collection;

/**
 * Financial statement query layer (module 25) — no tables of its own.
 * Builds the income statement, balance sheet, cash flow and statement of
 * changes in equity directly from posted journal lines + the chart of accounts.
 */
class StatementService
{
    public function __construct(protected ReportService $reportService) {}

    public function filters(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null): array
    {
        return $this->reportService->filters($companyId, $fiscalYearId, $periodId);
    }

    /**
     * P&L for the selected window, with a Current / Year-to-date column pair.
     */
    public function incomeStatement(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null): array
    {
        $filters = $this->filters($companyId, $fiscalYearId, $periodId);

        $fyStart = $this->fiscalYearStart($companyId, (int) $filters['selectedFiscalYearId']);

        $current = $this->signedBalances($companyId, [AccountType::Income, AccountType::Expense], $filters['from'], $filters['to']);
        $ytd = $this->signedBalances($companyId, [AccountType::Income, AccountType::Expense], $fyStart, $filters['to']);

        $rows = [];
        foreach ([AccountType::Income, AccountType::Expense] as $type) {
            $section = $this->flattenTree($companyId, [$type], $current, $ytd);
            $sectionTotal = (float) collect($section)->filter(fn ($r) => ! $r['is_group'] && $r['type'] === $type->value)->sum('current');
            $rows = array_merge($rows, $section);
            $rows[] = [
                'level' => 0, 'code' => '', 'name' => 'Total '.$type->label(), 'current' => round($sectionTotal, 4),
                'ytd' => round((float) collect($section)->filter(fn ($r) => ! $r['is_group'] && $r['type'] === $type->value)->sum('ytd'), 4),
                'is_group' => true, 'is_total' => true, 'type' => $type->value,
            ];
        }

        $income = (float) collect($rows)->filter(fn ($r) => ! $r['is_group'] && $r['type'] === 'income')->sum('current');
        $expense = (float) collect($rows)->filter(fn ($r) => ! $r['is_group'] && $r['type'] === 'expense')->sum('current');

        $incomeYtd = (float) collect($rows)->filter(fn ($r) => ! $r['is_group'] && $r['type'] === 'income')->sum('ytd');
        $expenseYtd = (float) collect($rows)->filter(fn ($r) => ! $r['is_group'] && $r['type'] === 'expense')->sum('ytd');

        return [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => [
                'income' => round($income, 4),
                'expense' => round($expense, 4),
                'net_income' => round($income - $expense, 4),
                'income_ytd' => round($incomeYtd, 4),
                'expense_ytd' => round($expenseYtd, 4),
                'net_income_ytd' => round($incomeYtd - $expenseYtd, 4),
            ],
        ];
    }

    /**
     * Balance sheet as at the end of the selected window (cumulative from the
     * fiscal year start). Equity includes a synthetic Current Year Earnings row.
     */
    public function balanceSheet(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null): array
    {
        $filters = $this->filters($companyId, $fiscalYearId, $periodId);

        $fyStart = $this->fiscalYearStart($companyId, (int) $filters['selectedFiscalYearId']);

        $balances = $this->signedBalances(
            $companyId,
            [AccountType::Asset, AccountType::Liability, AccountType::Equity],
            $fyStart,
            $filters['to']
        );

        $assets = $this->flattenTree($companyId, [AccountType::Asset], $balances, null);
        $liabilities = $this->flattenTree($companyId, [AccountType::Liability], $balances, null);
        $equity = $this->flattenTree($companyId, [AccountType::Equity], $balances, null);

        $assetTotal = (float) collect($assets)->filter(fn ($r) => ! $r['is_group'])->sum('current');
        $liabilityTotal = (float) collect($liabilities)->filter(fn ($r) => ! $r['is_group'])->sum('current');
        $equityAccountsTotal = (float) collect($equity)->filter(fn ($r) => ! $r['is_group'])->sum('current');

        $currentEarnings = $this->netIncome($companyId, $fyStart, $filters['to']);

        $equity[] = [
            'level' => 0, 'code' => '', 'name' => 'Current Year Earnings',
            'current' => round($currentEarnings, 4), 'ytd' => round($currentEarnings, 4),
            'is_group' => true, 'is_total' => true, 'type' => 'equity', 'synthetic' => true,
        ];

        $equityTotal = round($equityAccountsTotal + $currentEarnings, 4);
        $assetsTotal = round($assetTotal, 4);
        $liabEquityTotal = round($liabilityTotal + $equityTotal, 4);

        return [
            'filters' => $filters,
            'sections' => [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
            ],
            'totals' => [
                'assets' => $assetsTotal,
                'liabilities' => $liabilityTotal,
                'equity' => $equityTotal,
                'liabilities_equity' => $liabEquityTotal,
                'difference' => round($assetsTotal - $liabEquityTotal, 4),
            ],
        ];
    }

    /**
     * Direct-method cash flow classified by the counterpart account of every
     * posted journal that touches a cash or bank account.
     */
    public function cashFlow(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null): array
    {
        $filters = $this->filters($companyId, $fiscalYearId, $periodId);

        $fyStart = $this->fiscalYearStart($companyId, (int) $filters['selectedFiscalYearId']);

        $cashAccounts = $this->cashAccounts($companyId);
        $cashIds = $cashAccounts->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $opening = $this->signedBalanceForIds($companyId, $cashIds, $fyStart, date('Y-m-d', strtotime($filters['from'].' -1 day')));
        $closing = $this->signedBalanceForIds($companyId, $cashIds, $fyStart, $filters['to']);

        $journals = JournalLine::query()
            ->selectRaw('journal_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->whereHas('journal', function ($q) use ($companyId, $filters) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$filters['from'], $filters['to']]);
            })
            ->whereIn('account_id', $cashIds)
            ->groupBy('journal_id')
            ->get();

        $categories = ['operating' => 0, 'investing' => 0, 'financing' => 0];

        foreach ($journals as $journal) {
            $counterpart = JournalLine::query()
                ->where('journal_id', $journal->journal_id)
                ->whereNotIn('account_id', $cashIds)
                ->with(['account:id,id,code,type'])
                ->get();

            foreach ($counterpart as $line) {
                $category = $this->classify($line->account);
                $categories[$category] += (float) $line->debit - (float) $line->credit;
            }
        }

        $rows = [
            ['key' => 'operating', 'name' => 'Operating Activities', 'amount' => round(-$categories['operating'], 4)],
            ['key' => 'investing', 'name' => 'Investing Activities', 'amount' => round(-$categories['investing'], 4)],
            ['key' => 'financing', 'name' => 'Financing Activities', 'amount' => round(-$categories['financing'], 4)],
        ];

        $change = (float) collect($rows)->sum('amount');

        return [
            'filters' => $filters,
            'rows' => $rows,
            'opening' => round($opening, 4),
            'closing' => round($closing, 4),
            'net_change' => round($change, 4),
            'reconciled' => abs(($opening + $change) - $closing) < 0.01,
        ];
    }

    /**
     * Statement of changes in equity: opening (before the FY), movement in the
     * window, and closing balance per equity account plus current-year earnings.
     */
    public function changesInEquity(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null): array
    {
        $filters = $this->filters($companyId, $fiscalYearId, $periodId);

        $fyStart = $this->fiscalYearStart($companyId, (int) $filters['selectedFiscalYearId']);

        $opening = $this->signedBalances($companyId, [AccountType::Equity], $fyStart, date('Y-m-d', strtotime($filters['from'].' -1 day')));
        $closing = $this->signedBalances($companyId, [AccountType::Equity], $fyStart, $filters['to']);

        $rows = [];

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->where('type', AccountType::Equity->value)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'normal_balance']);

        foreach ($accounts as $account) {
            $openBal = (float) ($opening[$account->id] ?? 0);
            $closeBal = (float) ($closing[$account->id] ?? 0);
            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'opening' => round($openBal, 4),
                'movement' => round($closeBal - $openBal, 4),
                'closing' => round($closeBal, 4),
            ];
        }

        $earnings = $this->netIncome($companyId, $fyStart, $filters['to']);

        $rows[] = [
            'code' => '', 'name' => 'Current Year Earnings', 'synthetic' => true,
            'opening' => 0.0,
            'movement' => round($earnings, 4),
            'closing' => round($earnings, 4),
        ];

        return [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => [
                'opening' => round((float) collect($rows)->sum('opening'), 4),
                'movement' => round((float) collect($rows)->sum('movement'), 4),
                'closing' => round((float) collect($rows)->sum('closing'), 4),
            ],
        ];
    }

    // ───────────────────────── helpers ─────────────────────────

    protected function fiscalYearStart(int $companyId, int $fiscalYearId): string
    {
        $fy = FiscalYear::query()->where('company_id', $companyId)->find($fiscalYearId);

        if (! $fy) {
            return today()->startOfYear()->toDateString();
        }

        return $fy->start_date->toDateString();
    }

    /**
     * Signed balances (normal-balance positive) per account, grouped by type.
     */
    protected function signedBalances(int $companyId, array $types, string $from, string $to): array
    {
        $query = Account::query()
            ->select(['id', 'code', 'normal_balance'])
            ->where('company_id', $companyId)
            ->whereIn('type', array_map(fn ($t) => $t->value, $types));

        $accounts = $query->get()->keyBy('id');

        if ($accounts->isEmpty()) {
            return [];
        }

        $aggregates = JournalLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->whereIn('account_id', $accounts->keys())
            ->whereHas('journal', function ($q) use ($companyId, $from, $to) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$from, $to]);
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $balances = [];
        foreach ($accounts as $id => $account) {
            $debit = (float) ($aggregates[$id]->debit ?? 0);
            $credit = (float) ($aggregates[$id]->credit ?? 0);
            $normal = $account->normal_balance ?: AccountType::from($account->type ?? 'asset')->normalBalance();
            $balances[(int) $id] = round($normal === 'debit' ? $debit - $credit : $credit - $debit, 4);
        }

        return $balances;
    }

    protected function signedBalanceForIds(int $companyId, array $ids, string $from, string $to): float
    {
        if ($ids === [] || $from >= $to) {
            return 0.0;
        }

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->get(['id', 'normal_balance'])
            ->keyBy('id');

        $aggregates = JournalLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->whereIn('account_id', $ids)
            ->whereHas('journal', function ($q) use ($companyId, $from, $to) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$from, $to]);
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $total = 0.0;
        foreach ($accounts as $id => $account) {
            $debit = (float) ($aggregates[$id]->debit ?? 0);
            $credit = (float) ($aggregates[$id]->credit ?? 0);
            $normal = $account->normal_balance;
            $total += $normal === 'debit' ? $debit - $credit : $credit - $debit;
        }

        return $total;
    }

    /**
     * Net income over a date range: income signed total minus expense signed total.
     */
    protected function netIncome(int $companyId, string $from, string $to): float
    {
        $income = $this->signedBalances($companyId, [AccountType::Income], $from, $to);
        $expense = $this->signedBalances($companyId, [AccountType::Expense], $from, $to);

        return round(array_sum($income) - array_sum($expense), 4);
    }

    /**
     * Flatten the account tree for the given types into statement rows with
     * parent totals rolled up from their postable leaves.
     */
    protected function flattenTree(int $companyId, array $types, array $balances, ?array $ytd): array
    {
        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('type', array_map(fn ($t) => $t->value, $types))
            ->orderBy('code')
            ->get(['id', 'parent_id', 'code', 'name', 'type', 'normal_balance']);

        $children = [];
        foreach ($accounts as $account) {
            $children[(int) $account->parent_id][] = $account;
        }

        $rows = [];
        $this->walk($children, (int) $accounts->first()?->parent_id, $balances, $ytd, 0, $rows);

        return $rows;
    }

    protected function walk(array $children, int $parentId, array $balances, ?array $ytd, int $level, array &$rows): void
    {
        foreach ($children[$parentId] ?? [] as $account) {
            if (! isset($children[$account->id])) {
                $rows[] = $this->leafRow($account, $balances, $ytd, $level);
                continue;
            }

            $childRows = [];
            $this->walk($children, (int) $account->id, $balances, $ytd, $level + 1, $childRows);

            $balance = round((float) collect($childRows)->filter(fn ($r) => ! $r['is_group'])->sum('current'), 4);
            $ytdBalance = $ytd === null
                ? $balance
                : round((float) collect($childRows)->filter(fn ($r) => ! $r['is_group'])->sum('ytd'), 4);

            $rows[] = [
                'level' => $level, 'code' => $account->code, 'name' => $account->name,
                'current' => $balance, 'ytd' => $ytdBalance,
                'is_group' => true, 'is_total' => false, 'type' => $account->type,
            ];
            $rows = array_merge($rows, $childRows);
        }
    }

    protected function leafRow(Account $account, array $balances, ?array $ytd, int $level): array
    {
        return [
            'level' => $level, 'code' => $account->code, 'name' => $account->name,
            'current' => round((float) ($balances[$account->id] ?? 0), 4),
            'ytd' => $ytd === null ? round((float) ($balances[$account->id] ?? 0), 4) : round((float) ($ytd[$account->id] ?? 0), 4),
            'is_group' => false, 'is_total' => false, 'type' => $account->type,
        ];
    }

    /**
     * Postable cash & bank leaves (codes 111x / 112x) used by the cash-flow view.
     */
    protected function cashAccounts(int $companyId): Collection
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->where('type', AccountType::Asset->value)
            ->where('is_postable', true)
            ->where(function ($q) {
                $q->where('code', 'like', '111%')->orWhere('code', 'like', '112%');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    protected function classify(Account $account): string
    {
        $type = $account->type;
        $code = (string) $account->code;

        if (in_array($type, [AccountType::Income->value, AccountType::Expense->value], true)) {
            return 'operating';
        }

        if ($type === AccountType::Liability->value) {
            return str_starts_with($code, '22') || str_starts_with($code, '215') ? 'financing' : 'operating';
        }

        if ($type === AccountType::Equity->value) {
            return 'financing';
        }

        // Asset: non-current (12xx) is investing, everything else is working capital
        return str_starts_with($code, '12') ? 'investing' : 'operating';
    }
}