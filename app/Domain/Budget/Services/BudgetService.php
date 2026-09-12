<?php

namespace App\Domain\Budget\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Budget\Exceptions\BudgetPostingException;
use App\Domain\Budget\Models\Budget;
use App\Domain\Budget\Models\BudgetLine;
use App\Support\Enums\AccountType;
use App\Support\Enums\BudgetStatus;
use Illuminate\Support\Facades\Auth;

class BudgetService
{
    private const BUDGETABLE_ACCOUNT_TYPES = [AccountType::Income->value, AccountType::Expense->value];

    public function storeBudget(array $payload, int $companyId): Budget
    {
        $this->assertNoBudgetForFiscalYear($companyId, (int) $payload['fiscal_year_id'], null);
        $this->assertPayload($payload, $companyId);

        $budget = Budget::query()->create([
            'company_id' => $companyId,
            'fiscal_year_id' => $payload['fiscal_year_id'],
            'name' => trim($payload['name']),
            'status' => BudgetStatus::Draft->value,
            'notes' => $payload['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->syncLines($budget, $payload['lines']);

        return $budget->fresh();
    }

    public function updateBudget(Budget $budget, array $payload, int $companyId): Budget
    {
        $this->assertEditable($budget);
        $this->assertNoBudgetForFiscalYear($companyId, (int) $payload['fiscal_year_id'], $budget->id);
        $this->assertPayload($payload, $companyId);

        $budget->update([
            'fiscal_year_id' => $payload['fiscal_year_id'],
            'name' => trim($payload['name']),
            'notes' => $payload['notes'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        $this->syncLines($budget, $payload['lines']);

        return $budget->fresh();
    }

    public function destroyBudget(Budget $budget): void
    {
        $this->assertEditable($budget);

        $budget->lines()->forceDelete();
        $budget->delete();
    }

    public function postBudget(Budget $budget): void
    {
        if ($budget->status === BudgetStatus::Posted->value) {
            throw new BudgetPostingException('This budget is already posted.');
        }

        if ($budget->lines()->count() === 0) {
            throw new BudgetPostingException('A budget cannot be posted without budget lines.');
        }

        $budget->update(['status' => BudgetStatus::Posted->value]);
    }

    /**
     * Per-account budget vs actual variance for the budget's fiscal year.
     */
    public function variance(Budget $budget): array
    {
        $fy = $budget->fiscalYear;
        $periods = $fy->periods()->orderBy('start_date')->get(['id', 'name']);

        $accountIds = $budget->lines()->pluck('account_id')->unique()->values()->all();

        $actualTotals = $this->actualTotals($budget->company_id, $accountIds, $fy->start_date->toDateString(), $fy->end_date->toDateString());

        $accounts = Account::query()->whereIn('id', $accountIds)->get(['id', 'code', 'name', 'type', 'normal_balance'])->keyBy('id');

        $periodKeys = $periods->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rows = [];

        $lines = $budget->lines()->get();

        foreach ($accounts as $account) {
            $budgeted = $lines->where('account_id', $account->id)->sum('budgeted_amount');
            $actual = (float) ($actualTotals[$account->id]['balance'] ?? 0);
            $variance = $budgeted - $actual;
            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'budgeted' => round($budgeted, 4),
                'actual' => round($actual, 4),
                'variance' => round($variance, 4),
                'variance_pct' => $budgeted > 0 ? round($variance / $budgeted * 100, 2) : null,
            ];
        }

        // Per-period budgeted + actual for each budgeted (account, period) pair.
        $actualPerPeriod = $this->actualPerPeriod($budget->company_id, $accountIds, $fy->start_date->toDateString(), $fy->end_date->toDateString());

        $detail = [];

        foreach ($lines->sortBy(fn ($l) => [$l->account->code ?? '', $l->period->start_date ?? '']) as $line) {
            $periodId = (int) $line->period_id;
            $detail[] = [
                'id' => $line->id,
                'account_code' => $accounts->get($line->account_id)?->code ?? (string) $line->account_id,
                'account_name' => $accounts->get($line->account_id)?->name ?? '—',
                'period_id' => $periodId,
                'period_name' => $line->period?->name ?? '—',
                'budgeted_amount' => (float) $line->budgeted_amount,
                'actual_amount' => round((float) ($actualPerPeriod[$line->account_id][$periodId] ?? 0), 4),
            ];
        }

        return [
            'periods' => $periods->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name])->values()->all(),
            'rows' => $rows,
            'detail' => $detail,
        ];
    }

    protected function syncLines(Budget $budget, array $lines): void
    {
        $budget->lines()->forceDelete();

        foreach ($lines as $line) {
            $amount = (float) ($line['budgeted_amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            BudgetLine::query()->create([
                'budget_id' => $budget->id,
                'account_id' => $line['account_id'],
                'period_id' => $line['period_id'],
                'budgeted_amount' => $amount,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        }
    }

    protected function assertEditable(Budget $budget): void
    {
        if ($budget->status !== BudgetStatus::Draft->value) {
            throw new BudgetPostingException('Only draft budgets can be edited.');
        }
    }

    protected function assertNoBudgetForFiscalYear(int $companyId, int $fiscalYearId, ?int $exceptId): void
    {
        $exists = Budget::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw new BudgetPostingException('A budget already exists for this fiscal year.');
        }
    }

    protected function assertPayload(array $payload, int $companyId): void
    {
        $fiscalYearId = (int) $payload['fiscal_year_id'];

        $fiscalYear = FiscalYear::query()
            ->where('company_id', $companyId)
            ->find($fiscalYearId);

        if (! $fiscalYear) {
            throw new BudgetPostingException('Invalid fiscal year.');
        }

        $periodIds = $fiscalYear->periods()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $accountIds = [];

        foreach ($payload['lines'] as $line) {
            $lineAmount = (float) ($line['budgeted_amount'] ?? 0);

            if ($lineAmount <= 0) {
                continue;
            }

            $periodId = (int) $line['period_id'];
            $accountId = (int) $line['account_id'];

            if (! in_array($periodId, $periodIds, true)) {
                throw new BudgetPostingException('A budget line references a period outside the selected fiscal year.');
            }

            $account = Account::query()->where('company_id', $companyId)->find($accountId);

            if (! $account || ! $account->is_postable || ! in_array($account->type, self::BUDGETABLE_ACCOUNT_TYPES, true)) {
                throw new BudgetPostingException('Budget lines may only target postable income or expense accounts.');
            }

            $key = $accountId.'-'.$periodId;

            if (isset($accountIds[$key])) {
                throw new BudgetPostingException('Duplicate budget line for the same account and period.');
            }

            $accountIds[$key] = true;
        }
    }

    protected function actualTotals(int $companyId, array $accountIds, string $from, string $to): array
    {
        $rows = JournalLine::query()
            ->whereIn('account_id', $accountIds)
            ->whereHas('journal', function ($q) use ($companyId, $from, $to) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$from, $to]);
            })
            ->get(['account_id', 'debit', 'credit']);

        $accounts = Account::query()->whereIn('id', $accountIds)->get(['id', 'normal_balance'])->keyBy('id');

        $totals = [];

        foreach ($rows->groupBy('account_id') as $accountId => $group) {
            $debit = (float) $group->sum('debit');
            $credit = (float) $group->sum('credit');
            $normal = $accounts->get((int) $accountId)?->normal_balance ?? 'debit';
            $totals[(int) $accountId] = [
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $normal === 'debit' ? $debit - $credit : $credit - $debit,
            ];
        }

        return $totals;
    }

    protected function actualPerPeriod(int $companyId, array $accountIds, string $from, string $to): array
    {
        $rows = JournalLine::query()
            ->whereIn('account_id', $accountIds)
            ->whereHas('journal', function ($q) use ($companyId, $from, $to) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$from, $to]);
            })
            ->get(['account_id', 'debit', 'credit', 'journal_id']);

        $journals = Journal::query()
            ->whereIn('id', $rows->pluck('journal_id')->unique()->values())
            ->get(['id', 'period_id']);

        $periodOf = $journals->pluck('period_id', 'id');

        $accounts = Account::query()->whereIn('id', $accountIds)->get(['id', 'normal_balance'])->keyBy('id');

        $result = [];

        foreach ($rows as $row) {
            $periodId = (int) ($periodOf[$row->journal_id] ?? 0);
            $accountId = (int) $row->account_id;

            if ($periodId === 0) {
                continue;
            }

            $normal = $accounts->get($accountId)?->normal_balance ?? 'debit';

            if (! isset($result[$accountId][$periodId])) {
                $result[$accountId][$periodId] = ['debit' => 0, 'credit' => 0];
            }

            $result[$accountId][$periodId]['debit'] += (float) $row->debit;
            $result[$accountId][$periodId]['credit'] += (float) $row->credit;
        }

        $balances = [];

        foreach ($result as $accountId => $periods) {
            foreach ($periods as $periodId => $side) {
                $normal = $accounts->get($accountId)?->normal_balance ?? 'debit';
                $balances[$accountId][$periodId] = $normal === 'debit'
                    ? $side['debit'] - $side['credit']
                    : $side['credit'] - $side['debit'];
            }
        }

        return $balances;
    }
}