<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Domain\Accounting\Http\Requests\AccountingPeriodRequest;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Services\AccountingPeriodService;
use App\Domain\Audit\Services\AuditLogger;
use App\Support\Enums\PeriodStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingPeriodController
{
    public function index(Request $request): Response
    {
        $fiscalYearId = $request->integer('fiscal_year_id');

        $fiscalYears = FiscalYear::query()
            ->where('company_id', current_company_id())
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status']);

        $activeFiscalYearId = $fiscalYearId ?: FiscalYear::query()
            ->where('company_id', current_company_id())
            ->where('is_active', true)
            ->value('id');

        $periods = AccountingPeriod::query()
            ->where('fiscal_year_id', $activeFiscalYearId)
            ->with('fiscalYear:id,name')
            ->orderBy('start_date')
            ->get();

        return Inertia::render('Periods/Index', [
            'periods' => $periods,
            'fiscalYears' => $fiscalYears,
            'activeFiscalYearId' => $activeFiscalYearId,
            'statusOptions' => enum_options(PeriodStatus::class),
        ]);
    }

    public function store(AccountingPeriodRequest $request): RedirectResponse
    {
        $period = AccountingPeriod::query()->create($request->validated());

        AuditLogger::log('period', 'create', null, $period->id, [], $period->toArray(), current_company_id());

        return redirect()
            ->route('accounting-periods.index', ['fiscal_year_id' => $period->fiscal_year_id])
            ->with('success', 'Accounting period created.');
    }

    public function update(AccountingPeriodRequest $request, AccountingPeriod $period): RedirectResponse
    {
        AccountingPeriodService::assertCompanyPeriod($period);
        abort_if(! $period->isOpen(), 422, 'Only open periods can be edited.');

        $old = $period->toArray();
        $period->update($request->validated());

        AuditLogger::log('period', 'update', null, $period->id, $old, $period->toArray(), current_company_id());

        return back()->with('success', 'Accounting period updated.');
    }

    /**
     * Close a period — no further postings allowed (JournalPostingService
     * refuses closed periods). Earliest-open-first sequence enforced.
     */
    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin || $request->user()->hasPermission('period.close', current_company_id()), 403);

        try {
            AccountingPeriodService::close($period, $request->user(), current_company_id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Period [{$period->name}] closed.");
    }

    /**
     * Reopen a closed period (refused when any later period is locked).
     */
    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin || $request->user()->hasPermission('period.reopen', current_company_id()), 403);

        try {
            AccountingPeriodService::reopen($period, $request->user(), current_company_id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Period [{$period->name}] reopened.");
    }

    /**
     * Lock a period — read-only, no edits/postings/reopenings.
     */
    public function lock(Request $request, AccountingPeriod $period): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin || $request->user()->hasPermission('period.lock', current_company_id()), 403);

        try {
            AccountingPeriodService::lock($period, $request->user(), current_company_id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Period [{$period->name}] locked.");
    }

    /**
     * Set as the active working period — only open periods are allowed.
     */
    public function setActive(Request $request, AccountingPeriod $period): RedirectResponse
    {
        AccountingPeriodService::assertCanBeActive($period);

        AccountingPeriod::query()
            ->where('fiscal_year_id', $period->fiscal_year_id)
            ->update(['is_active' => false]);

        $period->update(['is_active' => true]);

        AuditLogger::log('period', 'set_active', null, $period->id, [], ['is_active' => true], current_company_id());

        return back()->with('success', "Period [{$period->name}] is now the active period.");
    }

    public function destroy(Request $request, AccountingPeriod $period): RedirectResponse
    {
        AccountingPeriodService::assertCanBeDeleted($period);

        $period->delete();

        AuditLogger::log('period', 'delete', null, $period->id, $period->toArray(), [], current_company_id());

        return back()->with('success', 'Accounting period deleted.');
    }
}