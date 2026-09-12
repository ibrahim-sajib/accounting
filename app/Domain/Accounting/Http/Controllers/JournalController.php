<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Domain\Accounting\Exceptions\JournalPostingException;
use App\Domain\Accounting\Http\Requests\JournalRequest;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Approval\Services\ApprovalWorkflowService;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class JournalController
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $journals = Journal::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->when($request->input('status') && $request->input('status') !== 'all', fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->input('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(fn ($q) => $q
                    ->where('journal_no', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Journal $journal) {
                $journal->total_debit = (float) $journal->lines->sum('debit');
                $journal->total_credit = (float) $journal->lines->sum('credit');
                $journal->lines_count = $journal->lines->count();
                unset($journal->lines);

                return $journal;
            });

        return Inertia::render('Journals/Index', [
            'journals' => $journals,
            'filters' => $request->only(['search', 'status']),
            'statusOptions' => [
                ['value' => 'all', 'label' => 'All statuses'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'posted', 'label' => 'Posted'],
                ['value' => 'reversed', 'label' => 'Reversed'],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Journals/Create', [
            'periods' => $this->openPeriods(),
            'accounts' => $this->postableAccounts(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(JournalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyId = current_company_id();

        $journal = Journal::query()->create([
            'company_id' => $companyId,
            'branch_id' => session('active_branch_id'),
            'period_id' => $data['period_id'],
            'journal_date' => $data['journal_date'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'source_type' => JournalSourceType::Manual->value,
            'status' => TransactionStatus::Draft->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $journal->lines()->createMany($this->normalizeLines($data['lines'] ?? []));

        \App\Domain\Audit\Services\AuditLogger::log('journal', 'create', null, $journal->id, [], $journal->toArray(), $companyId);

        return redirect()
            ->route('journals.show', $journal)
            ->with('success', 'Journal draft saved.');
    }

    public function show(Journal $journal): Response
    {
        $this->authorizeJournal($journal);

        $journal->load(['lines.account', 'period', 'createdBy', 'postedBy', 'reversal', 'origin', 'attachments.uploader:id,name']);

        $data = $journal->toArray();
        $data['attachments'] = \App\Domain\Document\Services\AttachmentService::serialize($journal->attachments);

        return Inertia::render('Journals/Show', [
            'journal' => $data,
        ]);
    }

    public function edit(Journal $journal): Response
    {
        $this->authorizeJournal($journal);

        abort_if(! $journal->isDraft(), 422, 'Only draft journals can be edited.');

        $journal->load('lines');

        return Inertia::render('Journals/Edit', [
            'journal' => $journal->only(['id', 'journal_date', 'period_id', 'reference', 'description']),
            'lines' => $journal->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'description' => $line->description,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
            ]),
            'periods' => $this->openPeriods(true),
            'accounts' => $this->postableAccounts(),
        ]);
    }

    public function update(JournalRequest $request, Journal $journal): RedirectResponse
    {
        $this->authorizeJournal($journal);

        abort_if(! $journal->isDraft(), 422, 'Only draft journals can be updated.');

        $old = $journal->lines()->get()->toArray();
        $data = $request->validated();

        $journal->update([
            'period_id' => $data['period_id'],
            'journal_date' => $data['journal_date'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        $journal->lines()->delete();
        $journal->lines()->createMany($this->normalizeLines($data['lines'] ?? []));

        \App\Domain\Audit\Services\AuditLogger::log('journal', 'update', null, $journal->id, $old, $journal->fresh()->lines->toArray(), $journal->company_id);

        return redirect()
            ->route('journals.show', $journal)
            ->with('success', 'Journal draft updated.');
    }

    public function post(Journal $journal): RedirectResponse
    {
        $this->authorizeJournal($journal);

        $amount = (float) $journal->lines()->sum('debit');

        $pendingApproval = app(ApprovalWorkflowService::class)
            ->submitForApproval('journal', $journal->company_id, $journal, $amount, auth()->id());
        if ($pendingApproval) {
            return back()->with('error', 'This journal exceeds the posting approval threshold — approval request #'.$pendingApproval->id.' is pending. Posting resumes once approved.');
        }

        try {
            $journal = $this->postingService->post($journal);

            return redirect()
                ->route('journals.show', $journal)
                ->with('success', "Journal {$journal->journal_no} posted.");
        } catch (JournalPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Journal $journal): RedirectResponse
    {
        $this->authorizeJournal($journal);

        try {
            $reversal = $this->postingService->reverse($journal);

            return redirect()
                ->route('journals.show', $reversal)
                ->with('success', "Journal {$journal->journal_no} reversed — reversal {$reversal->journal_no} created.");
        } catch (JournalPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Journal $journal): RedirectResponse
    {
        $this->authorizeJournal($journal);

        abort_if(! $journal->isDraft(), 422, 'Only draft journals can be deleted.');

        $companyId = $journal->company_id;
        $journal->delete();

        \App\Domain\Audit\Services\AuditLogger::log('journal', 'delete', null, $journal->id, [], [], $companyId);

        return redirect()
            ->route('journals.index')
            ->with('success', 'Draft journal deleted.');
    }

    private function authorizeJournal(Journal $journal): void
    {
        abort_if($journal->company_id !== current_company_id(), 403, 'This journal belongs to a different company.');
    }

    private function openPeriods(bool $includeCurrent = false): \Illuminate\Support\Collection
    {
        $query = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', current_company_id()))
            ->where('status', 'open')
            ->orderByDesc('start_date');

        $periods = $query->get(['id', 'name', 'fiscal_year_id']);

        return $periods->map(fn (AccountingPeriod $period) => [
            'value' => $period->id,
            'label' => $period->name,
        ]);
    }

    private function postableAccounts(): \Illuminate\Support\Collection
    {
        return Account::query()
            ->where('company_id', current_company_id())
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Account $account) => [
                'value' => $account->id,
                'label' => "{$account->code} — {$account->name}",
            ]);
    }

    private function normalizeLines(array $lines): array
    {
        return collect($lines)
            ->map(fn (array $line) => [
                'account_id' => $line['account_id'],
                'party_type' => null,
                'party_id' => null,
                'description' => $line['description'] ?? null,
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
            ])
            ->values()
            ->all();
    }
}