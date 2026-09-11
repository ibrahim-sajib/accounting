<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Exceptions\JournalPostingException;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Models\OpeningBalance;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalPostingService
{
    /**
     * Post a draft journal (manual or any source) → status posted.
     *
     * @throws JournalPostingException
     */
    public function post(Journal $journal): Journal
    {
        if ($journal->isPosted()) {
            throw new JournalPostingException('This journal is already posted.');
        }

        return DB::transaction(function () use ($journal) {
            $lines = $journal->lines()->orderBy('id')->get();

            $this->assertLinesValid($lines);
            $this->assertPeriodOpen($journal);

            $journal->update([
                'journal_no' => $journal->journal_no ?: $this->nextJournalNumber($journal),
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            $journal->load('lines');

            return $journal;
        });
    }

    /**
     * Create + post a manual journal from a request payload.
     */
    public function createManual(array $data, array $lines, ?int $companyId, ?int $branchId): Journal
    {
        return DB::transaction(function () use ($data, $lines, $companyId, $branchId) {
            $journal = Journal::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'period_id' => $data['period_id'] ?? null,
                'journal_date' => $data['journal_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'source_type' => JournalSourceType::Manual->value,
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany($this->normalizeLines($lines));

            $this->assertPeriodOpen($journal);

            $journal->update([
                'journal_no' => $this->nextJournalNumber($journal),
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            $journal->load('lines');

            return $journal;
        });
    }

    /**
     * Reverse a posted journal — creates an opposite posted journal (status "reversed" on the original).
     *
     * @throws JournalPostingException
     */
    public function reverse(Journal $journal): Journal
    {
        if ($journal->status === TransactionStatus::Reversed->value) {
            throw new JournalPostingException('This journal is already reversed.');
        }

        if (! $journal->isPosted()) {
            throw new JournalPostingException('Only posted journals can be reversed.');
        }

        return DB::transaction(function () use ($journal) {
            $this->assertPeriodOpen($journal, allowClosedBySuperAdmin: true);

            $reversal = Journal::query()->create([
                'company_id' => $journal->company_id,
                'branch_id' => $journal->branch_id,
                'period_id' => $journal->period_id,
                'journal_date' => $journal->journal_date,
                'reference' => 'Reversal of '.$journal->journal_no,
                'description' => 'Reversal of '.$journal->journal_no,
                'source_type' => $journal->source_type,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'reversed_journal_id' => $journal->id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($journal->lines as $line) {
                JournalLine::query()->create([
                    'journal_id' => $reversal->id,
                    'account_id' => $line->account_id,
                    'party_type' => $line->party_type,
                    'party_id' => $line->party_id,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                ]);
            }

            $journal->update([
                'status' => TransactionStatus::Reversed->value,
                'reversed_journal_id' => $reversal->id,
                'updated_by' => Auth::id(),
            ]);

            $reversal->update(['journal_no' => $this->nextJournalNumber($reversal)]);

            $reversal->load('lines');

            return $reversal;
        });
    }

    /**
     * Post opening balances for a fiscal year → a single posted "OB" journal dated the first day of the year.
     *
     * @param  array<int, array{account_id: int, debit: float|string, credit: float|string}>  $entries
     *
     * @throws JournalPostingException
     */
    public function postOpening(FiscalYear $fiscalYear, array $entries): Journal
    {
        if (! $fiscalYear->isOpen()) {
            throw new JournalPostingException('Opening balances can only be entered for an open fiscal year.');
        }

        $alreadyPosted = OpeningBalance::query()
            ->where('company_id', $fiscalYear->company_id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('status', TransactionStatus::Posted->value)
            ->exists();

        if ($alreadyPosted) {
            throw new JournalPostingException('Opening balances for this fiscal year are already posted.');
        }

        $existing = OpeningBalance::query()
            ->where('company_id', $fiscalYear->company_id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->with('account')
            ->get();

        $lines = collect($entries)->map(function (array $entry) use ($fiscalYear): array {
            $accountId = (int) $entry['account_id'];
            $account = Account::query()->find($accountId);

            if (! $account) {
                throw new JournalPostingException("Account #{$accountId} no longer exists.");
            }

            if ($account->company_id !== $fiscalYear->company_id) {
                throw new JournalPostingException("Account #{$accountId} does not belong to this company.");
            }

            if (! $account->is_postable) {
                throw new JournalPostingException("Account '{$account->name}' is a parent account and cannot receive postings.");
            }

            $debit = round((float) ($entry['debit'] ?? 0), 4);
            $credit = round((float) ($entry['credit'] ?? 0), 4);

            if ($debit > 0 && $credit > 0) {
                throw new JournalPostingException("Account '{$account->name}' must have either a debit OR a credit, not both.");
            }

            if ($debit == 0 && $credit == 0) {
                return [];
            }

            return [
                'account_id' => $accountId,
                'party_type' => null,
                'party_id' => null,
                'description' => "Opening balance — {$account->name}",
                'debit' => $debit,
                'credit' => $credit,
            ];
        })
            ->filter()
            ->values()
            ->all();

        // Include previously saved draft rows that were not resubmitted.
        $submittedIds = collect($entries)->pluck('account_id')->map(fn ($id) => (int) $id)->all();

        foreach ($existing as $row) {
            if (in_array($row->account_id, $submittedIds, true)) {
                continue;
            }

            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $lines[] = [
                'account_id' => $row->account_id,
                'party_type' => $row->party_type,
                'party_id' => $row->party_id,
                'description' => "Opening balance — {$row->account->name}",
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (count($lines) < 2) {
            throw new JournalPostingException('A balanced opening balance entry needs at least two accounts.');
        }

        $this->assertBalanced($lines);

        return DB::transaction(function () use ($fiscalYear, $lines) {
            $journal = Journal::query()->create([
                'company_id' => $fiscalYear->company_id,
                'branch_id' => session('active_branch_id'),
                'period_id' => $fiscalYear->periods()->orderBy('start_date')->value('id'),
                'journal_date' => $fiscalYear->start_date,
                'reference' => 'Opening balances — '.$fiscalYear->name,
                'description' => 'Opening balances for fiscal year '.$fiscalYear->name,
                'source_type' => JournalSourceType::Opening->value,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journalNo = $this->nextJournalNumber($journal);
            $journal->update(['journal_no' => $journalNo]);

            $journal->lines()->createMany($lines);

            // Finalize the opening balances staging table to exactly mirror the posted journal lines,
            // so the entry screen always reflects what was actually posted.
            $lineAccountIds = collect($lines)->pluck('account_id')->map(fn ($id) => (int) $id)->all();

            OpeningBalance::query()
                ->where('company_id', $fiscalYear->company_id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->whereNotIn('account_id', $lineAccountIds)
                ->delete();

            foreach ($lines as $line) {
                OpeningBalance::query()->updateOrCreate(
                    [
                        'company_id' => $fiscalYear->company_id,
                        'fiscal_year_id' => $fiscalYear->id,
                        'account_id' => (int) $line['account_id'],
                    ],
                    [
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'status' => TransactionStatus::Posted->value,
                        'journal_id' => $journal->id,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]
                );
            }

            $journal->load('lines');

            return $journal;
        });
    }

    /**
     * The journal_no is only final once a journal reaches "posted" (drafts carry none).
     */
    public function nextJournalNumber(Journal $journal): string
    {
        $prefix = JournalSourceType::from($journal->source_type)->prefix();
        $year = Carbon::parse($journal->journal_date)->format('Y');

        $latest = Journal::query()
            ->where('company_id', $journal->company_id)
            ->where('journal_no', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('journal_no')
            ->value('journal_no');

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("{$prefix}-{$year}-")->before('-')->toString()) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    private function assertBalanced(array $lines): void
    {
        $debit = array_sum(array_map(fn ($l) => (float) $l['debit'], $lines));
        $credit = array_sum(array_map(fn ($l) => (float) $l['credit'], $lines));

        if (abs($debit - $credit) > 0.0001) {
            throw new JournalPostingException(
                'Journal is unbalanced: total debit '.number_format($debit, 4).' vs total credit '.number_format($credit, 4).'.'
            );
        }
    }

    private function assertLinesValid($lines): void
    {
        if ($lines->count() < 2) {
            throw new JournalPostingException('A journal entry must have at least two lines.');
        }

        foreach ($lines as $line) {
            $account = Account::query()->find($line->account_id);

            if (! $account) {
                throw new JournalPostingException('One of the selected accounts no longer exists.');
            }

            if (! $account->is_postable) {
                throw new JournalPostingException("Account '{$account->name}' is a parent account and cannot receive postings.");
            }

            if ((float) $line->debit == 0 && (float) $line->credit == 0) {
                throw new JournalPostingException('Every journal line needs a non-zero debit or credit amount.');
            }

            if ((float) $line->debit > 0 && (float) $line->credit > 0) {
                throw new JournalPostingException('A journal line cannot be both debit and credit.');
            }
        }

        $this->assertBalanced($lines->toArray());
    }

    private function assertPeriodOpen(Journal $journal, bool $allowClosedBySuperAdmin = false): void
    {
        $period = $journal->period_id
            ? AccountingPeriod::query()->find($journal->period_id)
            : AccountingPeriod::query()
                ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $journal->company_id))
                ->where('start_date', '<=', $journal->journal_date)
                ->where('end_date', '>=', $journal->journal_date)
                ->first();

        if (! $period) {
            throw new JournalPostingException('No accounting period covers the journal date. Open one first.');
        }

        if (! $period->isOpen() && ! ($allowClosedBySuperAdmin && (Auth::user()?->is_super_admin))) {
            throw new JournalPostingException("The accounting period '{$period->name}' is not open for posting.");
        }
    }

    private function normalizeLines(array $lines): array
    {
        return collect($lines)
            ->map(function (array $line) {
                return [
                    'account_id' => $line['account_id'],
                    'party_type' => $line['party_type'] ?? null,
                    'party_id' => $line['party_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    }