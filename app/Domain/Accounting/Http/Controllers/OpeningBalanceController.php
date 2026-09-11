<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Domain\Accounting\Exceptions\JournalPostingException;
use App\Domain\Accounting\Http\Requests\OpeningBalanceRequest;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\OpeningBalance;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OpeningBalanceController
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    public function index(): Response
    {
        $companyId = current_company_id();

        $fiscalYears = FiscalYear::query()
            ->where('company_id', $companyId)
            ->withCount('periods')
            ->orderByDesc('start_date')
            ->get()
            ->map(function (FiscalYear $fy) use ($companyId) {
                $draftCount = OpeningBalance::query()
                    ->where('company_id', $companyId)
                    ->where('fiscal_year_id', $fy->id)
                    ->where('status', TransactionStatus::Draft->value)
                    ->count();

                $postedCount = OpeningBalance::query()
                    ->where('company_id', $companyId)
                    ->where('fiscal_year_id', $fy->id)
                    ->where('status', TransactionStatus::Posted->value)
                    ->count();

                $journalId = OpeningBalance::query()
                    ->where('company_id', $companyId)
                    ->where('fiscal_year_id', $fy->id)
                    ->where('status', TransactionStatus::Posted->value)
                    ->value('journal_id');

                $fy->opening_status = $postedCount > 0 ? 'posted' : ($draftCount > 0 ? 'draft' : 'empty');
                $fy->opening_journal_id = $journalId;

                return $fy;
            });

        return Inertia::render('OpeningBalances/Index', [
            'fiscalYears' => $fiscalYears,
        ]);
    }

    public function entry(FiscalYear $fiscalYear): Response
    {
        $companyId = current_company_id();
        abort_if($fiscalYear->company_id !== $companyId, 403, 'This fiscal year belongs to a different company.');

        $posted = OpeningBalance::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('status', TransactionStatus::Posted->value)
            ->exists();

        $entries = OpeningBalance::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->with('account')
            ->get()
            ->map(fn (OpeningBalance $ob) => [
                'account_id' => $ob->account_id,
                'account_label' => "{$ob->account->code} — {$ob->account->name}",
                'debit' => (float) $ob->debit,
                'credit' => (float) $ob->credit,
                'status' => $ob->status,
            ]);

        $postedJournalId = $posted
            ? OpeningBalance::query()
                ->where('company_id', $companyId)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('status', TransactionStatus::Posted->value)
                ->value('journal_id')
            : null;

        return Inertia::render('OpeningBalances/Entry', [
            'fiscalYear' => $fiscalYear->only(['id', 'name', 'start_date', 'end_date', 'status']),
            'accounts' => $posted || ! $fiscalYear->isOpen()
                ? collect()
                : Account::query()
                    ->where('company_id', $companyId)
                    ->where('is_postable', true)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'type'])
                    ->map(fn (Account $account) => [
                        'value' => $account->id,
                        'label' => "{$account->code} — {$account->name}",
                    ]),
            'entries' => $entries,
            'posted' => $posted,
            'locked' => ! $fiscalYear->isOpen(),
            'postedJournalId' => $postedJournalId,
        ]);
    }

    public function save(OpeningBalanceRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $companyId = current_company_id();
        abort_if($fiscalYear->company_id !== $companyId, 403);
        abort_if(! $fiscalYear->isOpen(), 422, 'Opening balances can only be entered for an open fiscal year.');

        $alreadyPosted = OpeningBalance::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('status', TransactionStatus::Posted->value)
            ->exists();

        abort_if($alreadyPosted, 422, 'Opening balances for this fiscal year are already posted.');

        $entries = $request->validated('entries') ?? [];
        $submittedAccountIds = [];

        foreach ($entries as $entry) {
            $accountId = (int) $entry['account_id'];
            $debit = (float) $entry['debit'];
            $credit = (float) $entry['credit'];
            $submittedAccountIds[] = $accountId;

            if ($debit == 0 && $credit == 0) {
                OpeningBalance::query()
                    ->where('company_id', $companyId)
                    ->where('fiscal_year_id', $fiscalYear->id)
                    ->where('account_id', $accountId)
                    ->delete();

                continue;
            }

            OpeningBalance::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'fiscal_year_id' => $fiscalYear->id,
                    'account_id' => $accountId,
                ],
                [
                    'debit' => $debit,
                    'credit' => $credit,
                    'status' => TransactionStatus::Draft->value,
                    'updated_by' => Auth::id(),
                    'created_by' => Auth::id(),
                ]
            );
        }

        return redirect()
            ->route('opening-balances.entry', $fiscalYear)
            ->with('success', 'Opening balance draft saved.');
    }

    public function post(OpeningBalanceRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $companyId = current_company_id();
        abort_if($fiscalYear->company_id !== $companyId, 403);

        try {
            $journal = $this->postingService->postOpening($fiscalYear, $request->validated('entries') ?? []);

            \App\Domain\Audit\Services\AuditLogger::log('opening_balance', 'post', null, $journal->id, [], $journal->toArray(), $companyId);

            return redirect()
                ->route('journals.show', $journal)
                ->with('success', "Opening balances posted as journal {$journal->journal_no}.");
        } catch (JournalPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}