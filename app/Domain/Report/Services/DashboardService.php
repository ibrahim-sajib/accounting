<?php

namespace App\Domain\Report\Services;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\SalesInvoice;
use App\Support\Enums\AccountType;

/**
 * Accounting dashboard metrics (module 30) — another read-only query layer over
 * the ledger + subsidiary ledgers; no tables of its own.
 */
class DashboardService
{
    public function metrics(int $companyId): array
    {
        $fiscalYear = FiscalYear::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        $activePeriod = $fiscalYear
            ? AccountingPeriod::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->first()
            : null;

        $from = ($activePeriod?->start_date ?? $fiscalYear?->start_date ?? today())->toDateString();
        $to = ($activePeriod?->end_date ?? $fiscalYear?->end_date ?? today())->toDateString();

        return [
            'fiscal_year' => $fiscalYear?->name,
            'active_period' => $activePeriod?->name,
            'range' => ['from' => $from, 'to' => $to],
            'cash_balance' => $this->signedSum($companyId, ['111%', '112%'], null, null, $to),
            'receivables' => $this->receivableMetrics($companyId),
            'payables' => $this->payableMetrics($companyId),
            'activity' => $this->activity($companyId, $from, $to),
            'trial_balance' => $this->trialBalanceCheck($companyId, $from, $to),
            'recent_journals' => $this->recentJournals($companyId),
        ];
    }

    protected function activity(int $companyId, string $from, string $to): array
    {
        $income = $this->signedSum($companyId, null, AccountType::Income, $from, $to);
        $expense = $this->signedSum($companyId, null, AccountType::Expense, $from, $to);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => round($income - $expense, 4),
        ];
    }

    protected function receivableMetrics(int $companyId): array
    {
        $invoices = SalesInvoice::query()->where('company_id', $companyId)->where('status', 'posted')->get();

        $total = 0.0;
        $overdue = 0.0;
        $open = 0;

        foreach ($invoices as $invoice) {
            $due = $invoice->balanceDue();

            if ($due <= 0.01) {
                continue;
            }

            $total += $due;
            $open++;

            if ($invoice->due_date?->lessThan(today())) {
                $overdue += $due;
            }
        }

        return [
            'balance' => round($total, 4),
            'overdue' => round($overdue, 4),
            'open_invoices' => $open,
        ];
    }

    protected function payableMetrics(int $companyId): array
    {
        $bills = PurchaseBill::query()->where('company_id', $companyId)->where('status', 'posted')->get();

        $total = 0.0;
        $overdue = 0.0;
        $open = 0;

        foreach ($bills as $bill) {
            $due = $bill->balanceDue();

            if ($due <= 0.01) {
                continue;
            }

            $total += $due;
            $open++;

            if ($bill->due_date?->lessThan(today())) {
                $overdue += $due;
            }
        }

        return [
            'balance' => round($total, 4),
            'overdue' => round($overdue, 4),
            'open_bills' => $open,
        ];
    }

    protected function trialBalanceCheck(int $companyId, string $from, string $to): array
    {
        $aggregates = JournalLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->whereHas('journal', function ($q) use ($companyId, $from, $to) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$from, $to]);
            })
            ->groupBy('account_id')
            ->get();

        $totalDebit = round((float) $aggregates->sum('debit'), 4);
        $totalCredit = round((float) $aggregates->sum('credit'), 4);

        return [
            'debit' => $totalDebit,
            'credit' => $totalCredit,
            'difference' => round($totalDebit - $totalCredit, 4),
            'balanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    protected function recentJournals(int $companyId): array
    {
        return Journal::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'journal_no', 'journal_date', 'source_type', 'description', 'status'])
            ->map(fn ($j) => [
                'id' => $j->id,
                'journal_no' => $j->journal_no,
                'journal_date' => $j->journal_date->toDateString(),
                'source_type' => $j->source_type,
                'description' => $j->description,
            ])
            ->values()
            ->all();
    }

    /**
     * Signed balance of accounts matching either a code prefix or an account type.
     */
    protected function signedSum(int $companyId, ?array $prefixes, ?AccountType $type, ?string $from, string $to): float
    {
        $query = Account::query()
            ->select(['id', 'code', 'type', 'normal_balance'])
            ->where('company_id', $companyId)
            ->where('is_postable', true);

        if ($prefixes) {
            $query->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $i => $prefix) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}('code', 'like', $prefix);
                }
            });
        }

        if ($type) {
            $query->where('type', $type->value);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $lines = JournalLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->whereHas('journal', function ($q) use ($companyId, $from, $to) {
                $q->where('company_id', $companyId)
                    ->where('status', 'posted')
                    ->whereBetween('journal_date', [$from ?? '0000-01-01', $to]);
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $total = 0.0;
        foreach ($accounts as $account) {
            $debit = (float) ($lines[$account->id]->debit ?? 0);
            $credit = (float) ($lines[$account->id]->credit ?? 0);
            $normal = $account->normal_balance ?: AccountType::from($account->type)->normalBalance();
            $total += $normal === 'debit' ? $debit - $credit : $credit - $debit;
        }

        return round($total, 4);
    }
}