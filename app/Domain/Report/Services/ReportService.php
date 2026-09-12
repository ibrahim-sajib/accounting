<?php

namespace App\Domain\Report\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\JournalLine;
use App\Support\Enums\AccountType;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Resolve the fiscal year / period date range for a report, falling back
     * to the most recent fiscal year when none is selected.
     */
    public function filters(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null): array
    {
        $fiscalYears = FiscalYear::query()
            ->where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date']);

        $fy = $fiscalYears->firstWhere('id', (int) $fiscalYearId) ?? $fiscalYears->first();

        $periods = $fy
            ? $fy->periods()->orderBy('start_date')->get(['id', 'name', 'fiscal_year_id', 'start_date', 'end_date'])
            : collect();

        $period = $periods->firstWhere('id', (int) $periodId);

        $from = $period ? $period->start_date->toDateString() : ($fy?->start_date->toDateString() ?? today()->toDateString());
        $to = $period ? $period->end_date->toDateString() : ($fy?->end_date->toDateString() ?? today()->toDateString());

        return [
            'fiscalYears' => $fiscalYears->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->values()->all(),
            'periods' => $periods->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'fiscal_year_id' => $p->fiscal_year_id])->values()->all(),
            'selectedFiscalYearId' => $fy?->id,
            'selectedPeriodId' => $period?->id,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * General ledger entries for the range, with a running balance per account
     * that carries forward the account's balance from the start of the fiscal year.
     */
    public function generalLedger(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null, ?int $accountId = null): array
    {
        $filters = $this->filters($companyId, $fiscalYearId, $periodId);

        $lines = JournalLine::query()
            ->select(['id', 'journal_id', 'account_id', 'description', 'debit', 'credit'])
            ->whereHas('journal', function ($q) use ($companyId, $filters) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$filters['from'], $filters['to']]);
            })
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->with(['journal:id,journal_no,journal_date,source_type,reference', 'account:id,code,name,normal_balance'])
            ->get()
            ->sortBy(fn ($l) => [$l->journal->journal_date, $l->journal_id])
            ->values();

        $accounts = $filters['fiscalYears'] === [] ? collect() : $this->accountIndex(
            (int) $filters['selectedFiscalYearId'],
            $companyId,
            $lines->pluck('account_id')->unique()->values()->all()
        );

        $prior = $this->priorBalances($companyId, (int) $filters['selectedFiscalYearId'], $filters['from'], $lines->pluck('account_id')->unique()->values()->all());

        $entries = [];
        $running = $prior;

        foreach ($lines as $line) {
            $accountIdKey = (int) $line->account_id;
            $normal = $accounts[$accountIdKey]['normal_balance'] ?? 'debit';
            $delta = $normal === 'debit' ? (float) $line->debit - (float) $line->credit : (float) $line->credit - (float) $line->debit;

            $running[$accountIdKey] = round(($running[$accountIdKey] ?? 0) + $delta, 4);

            $entries[] = [
                'id' => $line->id,
                'journal_id' => $line->journal_id,
                'journal_no' => $line->journal->journal_no,
                'journal_date' => $line->journal->journal_date->toDateString(),
                'source_type' => $line->journal->source_type,
                'reference' => $line->journal->reference,
                'description' => $line->description ?: $line->journal->description,
                'account_id' => $accountIdKey,
                'account_code' => $accounts[$accountIdKey]['code'] ?? '',
                'account_name' => $accounts[$accountIdKey]['name'] ?? '',
                'debit' => round((float) $line->debit, 4),
                'credit' => round((float) $line->credit, 4),
                'running_balance' => $running[$accountIdKey],
            ];
        }

        $summary = [];
        $totals = ['debit' => 0, 'credit' => 0];

        foreach ($lines->groupBy('account_id') as $accountIdKey => $group) {
            $debit = round((float) $group->sum('debit'), 4);
            $credit = round((float) $group->sum('credit'), 4);
            $normal = $accounts[(int) $accountIdKey]['normal_balance'] ?? 'debit';
            $net = $normal === 'debit' ? $debit - $credit : $credit - $debit;

            $summary[] = [
                'account_id' => (int) $accountIdKey,
                'account_code' => $accounts[(int) $accountIdKey]['code'] ?? '',
                'account_name' => $accounts[(int) $accountIdKey]['name'] ?? '',
                'debit' => $debit,
                'credit' => $credit,
                'net' => round($net, 4),
                'closing_balance' => round(($running[(int) $accountIdKey] ?? 0), 4),
            ];

            $totals['debit'] = round($totals['debit'] + $debit, 4);
            $totals['credit'] = round($totals['credit'] + $credit, 4);
        }

        return [
            'filters' => $filters,
            'accounts' => $this->accountOptions($companyId),
            'entries' => $entries,
            'summary' => collect($summary)->sortBy('account_code')->values()->all(),
            'totals' => $totals,
        ];
    }

    /**
     * Classic two-column trial balance: each account's net balance is shown on
     * its normal side; the totals must match (signed difference zero).
     */
    public function trialBalance(int $companyId, ?int $fiscalYearId = null, ?int $periodId = null): array
    {
        $filters = $this->filters($companyId, $fiscalYearId, $periodId);

        $aggregates = JournalLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->whereHas('journal', function ($q) use ($companyId, $filters) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$filters['from'], $filters['to']]);
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $aggregates->keys())
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance']);

        $rows = [];
        $totals = ['debit' => 0, 'credit' => 0];

        foreach ($accounts as $account) {
            $debit = round((float) ($aggregates[$account->id]->debit ?? 0), 4);
            $credit = round((float) ($aggregates[$account->id]->credit ?? 0), 4);
            $normal = $account->normal_balance ?: AccountType::from($account->type)->normalBalance();
            $balance = $normal === 'debit' ? $debit - $credit : $credit - $debit;

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => AccountType::from($account->type)->label(),
                'debit' => $normal === 'debit' ? $balance : 0.0,
                'credit' => $normal === 'credit' ? $balance : 0.0,
                'balance' => round($balance, 4),
            ];

            $totals['debit'] = round($totals['debit'] + ($normal === 'debit' ? $balance : 0), 4);
            $totals['credit'] = round($totals['credit'] + ($normal === 'credit' ? $balance : 0), 4);
        }

        return [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => $totals,
            'balanced' => abs($totals['debit'] - $totals['credit']) < 0.01,
        ];
    }

    protected function accountIndex(int $fiscalYearId, int $companyId, array $accountIds): Collection
    {
        if ($accountIds === []) {
            return collect();
        }

        return Account::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $accountIds)
            ->get(['id', 'code', 'name', 'normal_balance'])
            ->keyBy('id');
    }

    /**
     * Balance carried into the report window: all posted lines from the fiscal
     * year start up to (but not including) the window start.
     */
    protected function priorBalances(int $companyId, int $fiscalYearId, string $from, array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $fy = FiscalYear::query()->where('company_id', $companyId)->find($fiscalYearId);

        if (! $fy) {
            return array_fill_keys(array_map('intval', $accountIds), 0);
        }

        $priorEnd = \Illuminate\Support\Carbon::parse($from)->subDay()->toDateString();

        $rows = JournalLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->whereIn('account_id', $accountIds)
            ->whereHas('journal', function ($q) use ($companyId, $fy, $priorEnd) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$fy->start_date->toDateString(), $priorEnd]);
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = $this->accountIndex($fiscalYearId, $companyId, $accountIds);
        $prior = [];

        foreach ($accountIds as $accountId) {
            $normal = $accounts[(int) $accountId]['normal_balance'] ?? 'debit';
            $debit = (float) ($rows[(int) $accountId]->debit ?? 0);
            $credit = (float) ($rows[(int) $accountId]->credit ?? 0);
            $prior[(int) $accountId] = round($normal === 'debit' ? $debit - $credit : $credit - $debit, 4);
        }

        return $prior;
    }

    protected function accountOptions(int $companyId): array
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['value' => $a->id, 'label' => $a->code.' — '.$a->name])
            ->values()
            ->all();
    }
}