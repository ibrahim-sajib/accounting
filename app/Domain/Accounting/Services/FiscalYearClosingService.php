<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Exceptions\FiscalYearClosingException;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Audit\Services\AuditLogger;
use App\Support\Enums\AccountType;
use App\Support\Enums\FiscalYearStatus;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\PeriodStatus;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Year-end closing: books the net income/expense to Retained Earnings, carries
 * asset/liability/equity balances forward as opening balances for the next
 * fiscal year, then marks the year closed.
 */
class FiscalYearClosingService
{
    public function __construct(private readonly JournalPostingService $postingService)
    {
    }

    public function close(FiscalYear $fiscalYear): array
    {
        return DB::transaction(function () use ($fiscalYear) {
            if (! $fiscalYear->isOpen()) {
                throw new FiscalYearClosingException("Fiscal year '{$fiscalYear->name}' is already closed.");
            }

            if ($fiscalYear->is_active) {
                throw new FiscalYearClosingException('Move the active flag to the next fiscal year before closing this one.');
            }

            $openPeriods = $fiscalYear->periods()
                ->where('status', PeriodStatus::Open->value)
                ->count();

            if ($openPeriods > 0) {
                throw new FiscalYearClosingException('Close all accounting periods before closing the fiscal year.');
            }

            $audit = ['status' => 'closed'];

            $closing = $this->postClosingJournal($fiscalYear);
            if ($closing) {
                $audit['closing_journal_id'] = $closing->id;
            }

            $opening = $this->postCarryForwardOpening($fiscalYear);
            if ($opening) {
                $audit['opening_journal_id'] = $opening->id;
            }

            $fiscalYear->update(['status' => FiscalYearStatus::Closed->value]);

            AuditLogger::log('fiscal_year', 'close', null, $fiscalYear->id, [], $audit, $fiscalYear->company_id);

            return ['closing' => $closing, 'opening' => $opening];
        });
    }

    public function reopen(FiscalYear $fiscalYear): void
    {
        if ($fiscalYear->isOpen()) {
            throw new FiscalYearClosingException('This fiscal year is already open.');
        }

        $laterClosed = FiscalYear::query()
            ->where('company_id', $fiscalYear->company_id)
            ->where('start_date', '>', $fiscalYear->end_date)
            ->where('status', FiscalYearStatus::Closed->value)
            ->exists();

        if ($laterClosed) {
            throw new FiscalYearClosingException('Cannot reopen this year while a later fiscal year is already closed.');
        }

        $fiscalYear->update(['status' => FiscalYearStatus::Open->value]);

        AuditLogger::log('fiscal_year', 'reopen', null, $fiscalYear->id, ['status' => 'closed'], ['status' => 'open'], $fiscalYear->company_id);
    }

    /**
     * Zero out income/expense into Retained Earnings (3211) as a posted YEC journal.
     */
    private function postClosingJournal(FiscalYear $fiscalYear): ?Journal
    {
        $balances = $this->signedBalances($fiscalYear);

        $incomeLines = collect($balances)
            ->filter(fn (array $b) => $b['account']->typeValue() === AccountType::Income)
            ->map(fn (array $b) => ['account_id' => $b['account']->id, 'net' => $b['net']])
            ->filter(fn (array $l) => abs($l['net']) > 0.0001)
            ->values();

        $expenseLines = collect($balances)
            ->filter(fn (array $b) => $b['account']->typeValue() === AccountType::Expense)
            ->map(fn (array $b) => ['account_id' => $b['account']->id, 'net' => $b['net']])
            ->filter(fn (array $l) => abs($l['net']) > 0.0001)
            ->values();

        if ($incomeLines->isEmpty() && $expenseLines->isEmpty()) {
            return null;
        }

        $incomeTotal = $incomeLines->sum('net');
        $expenseTotal = $expenseLines->sum('net');

        $retainedEarnings = Account::query()
            ->where('company_id', $fiscalYear->company_id)
            ->where('code', '3211')
            ->first();

        if (! $retainedEarnings) {
            throw new FiscalYearClosingException('Retained Earnings account (3211) not found. Re-run the chart of accounts seeder.');
        }

        if (! $retainedEarnings->is_postable) {
            throw new FiscalYearClosingException('Retained Earnings account (3211) is not postable.');
        }

        $lines = [];

        foreach ($incomeLines as $line) {
            // income is credit-normal; closing debits it to zero
            $lines[] = [
                'account_id' => $line['account_id'],
                'description' => "Close income — {$fiscalYear->name}",
                'debit' => $line['net'],
                'credit' => 0,
            ];
        }

        foreach ($expenseLines as $line) {
            // expense is debit-normal; closing credits it to zero
            $lines[] = [
                'account_id' => $line['account_id'],
                'description' => "Close expense — {$fiscalYear->name}",
                'debit' => 0,
                'credit' => $line['net'],
            ];
        }

        $netIncome = $incomeTotal - $expenseTotal;

        if (abs($netIncome) > 0.0001) {
            $lines[] = [
                'account_id' => $retainedEarnings->id,
                'description' => "Close {$fiscalYear->name} net income to retained earnings",
                'debit' => $netIncome > 0 ? 0 : abs($netIncome),
                'credit' => $netIncome > 0 ? $netIncome : 0,
            ];
        }

        return DB::transaction(function () use ($fiscalYear, $lines, $retainedEarnings) {
            $lastPeriod = $fiscalYear->periods()->orderByDesc('start_date')->firstOrFail();

            $journal = Journal::query()->create([
                'company_id' => $fiscalYear->company_id,
                'branch_id' => session('active_branch_id'),
                'period_id' => $lastPeriod->id,
                'journal_date' => $fiscalYear->end_date,
                'reference' => 'Year-End Closing — '.$fiscalYear->name,
                'description' => 'Year-end closing entries for fiscal year '.$fiscalYear->name,
                'source_type' => JournalSourceType::Closing->value,
                'source_id' => $fiscalYear->id,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->update(['journal_no' => $this->postingService->nextJournalNumber($journal)]);

            $journal->lines()->createMany($lines);

            $journal->load('lines');

            return $journal;
        });
    }

    /**
     * Carry asset/liability/equity net balances into the next fiscal year as an opening journal.
     */
    private function postCarryForwardOpening(FiscalYear $fiscalYear): ?Journal
    {
        $next = FiscalYear::query()
            ->where('company_id', $fiscalYear->company_id)
            ->where('start_date', '>', $fiscalYear->end_date)
            ->orderBy('start_date')
            ->first();

        if (! $next) {
            return null;
        }

        $firstPeriod = $next->periods()->orderBy('start_date')->first();

        if (! $firstPeriod) {
            return null;
        }

        $balances = $this->signedBalances($fiscalYear);

        $lines = [];

        foreach ($balances as $entry) {
            $account = $entry['account'];
            $net = $entry['net'];

            if (abs($net) <= 0.0001) {
                continue;
            }

            if ($account->typeValue() === AccountType::Income || $account->typeValue() === AccountType::Expense) {
                continue;
            }

            // asset is debit-normal: a positive net carries as a debit; a negative flips to a credit.
            $debit = 0;
            $credit = 0;

            if ($account->typeValue() === AccountType::Asset) {
                $net > 0 ? $debit = $net : $credit = abs($net);
            } else {
                $net > 0 ? $credit = $net : $debit = abs($net);
            }

            $lines[] = [
                'account_id' => $account->id,
                'description' => "Carry forward — {$account->name}",
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (count($lines) < 2) {
            return null;
        }

        $this->postingService->assertBalanced($lines);

        return DB::transaction(function () use ($next, $firstPeriod, $lines, $fiscalYear) {
            $journal = Journal::query()->create([
                'company_id' => $next->company_id,
                'branch_id' => session('active_branch_id'),
                'period_id' => $firstPeriod->id,
                'journal_date' => $next->start_date,
                'reference' => 'Opening balances — '.$next->name,
                'description' => 'Opening balances carried forward from '.$fiscalYear->name,
                'source_type' => JournalSourceType::Opening->value,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->update(['journal_no' => $this->postingService->nextJournalNumber($journal)]);

            $journal->lines()->createMany($lines);

            $journal->load('lines');

            return $journal;
        });
    }

    /**
     * Signed net balance per postable account from POSTED journal lines in the fiscal year range.
     *
     * @return array<int, array{account: Account, net: float}>
     */
    private function signedBalances(FiscalYear $fiscalYear): array
    {
        $rows = JournalLine::query()
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journals.company_id', $fiscalYear->company_id)
            ->where('journals.status', TransactionStatus::Posted->value)
            ->whereBetween('journals.journal_date', [
                $fiscalYear->start_date->copy()->startOfDay(),
                $fiscalYear->end_date->copy()->endOfDay(),
            ])
            ->groupBy('journal_lines.account_id')
            ->get();

        $accountsById = Account::query()
            ->where('company_id', $fiscalYear->company_id)
            ->whereIn('id', $rows->pluck('account_id'))
            ->get()
            ->keyBy('id');

        $balances = [];

        foreach ($rows as $row) {
            $account = $accountsById->get($row->account_id);

            if (! $account || ! $account->is_postable) {
                continue;
            }

            $net = $account->typeValue()->normalBalance() === 'credit'
                ? (float) $row->total_credit - (float) $row->total_debit
                : (float) $row->total_debit - (float) $row->total_credit;

            $balances[] = ['account' => $account, 'net' => round($net, 4)];
        }

        return $balances;
    }
}