<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Domain\Accounting\Http\Requests\AccountingPeriodRequest;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
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

        \App\Domain\Audit\Services\AuditLogger::log('period', 'create', null, $period->id, [], $period->toArray(), current_company_id());

        return redirect()
            ->route('accounting-periods.index', ['fiscal_year_id' => $period->fiscal_year_id])
            ->with('success', 'Accounting period created.');
    }

    public function update(AccountingPeriodRequest $request, AccountingPeriod $period): RedirectResponse
    {
        $old = $period->toArray();
        $period->update($request->validated());

        \App\Domain\Audit\Services\AuditLogger::log('period', 'update', null, $period->id, $old, $period->toArray(), current_company_id());

        return back()->with('success', 'Accounting period updated.');
    }

    /**
     * Close a period — no normal postings allowed.
     */
    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin || $request->user()->hasPermission('period.close', current_company_id()), 403);

        $period->update(['status' => PeriodStatus::Closed->value]);

        \App\Domain\Audit\Services\AuditLogger::log('period', 'close', null, $period->id, [], ['status' => 'closed'], current_company_id());

        return back()->with('success', "Period [{$period->name}] closed.");
    }

    /**
     * Reopen a period (requires permission).
     */
    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin || $request->user()->hasPermission('period.reopen', current_company_id()), 403);

        $period->update(['status' => PeriodStatus::Open->value]);

        \App\Domain\Audit\Services\AuditLogger::log('period', 'reopen', null, $period->id, [], ['status' => 'open'], current_company_id());

        return back()->with('success', "Period [{$period->name}] reopened.");
    }

    /**
     * Lock a period — read-only view, no edits/postings/reopenings.
     */
    public function lock(Request $request, AccountingPeriod $period): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin || $request->user()->hasPermission('period.lock', current_company_id()), 403);

        $period->update(['status' => PeriodStatus::Locked->value]);

        \App\Domain\Audit\Services\AuditLogger::log('period', 'lock', null, $period->id, [], ['status' => 'locked'], current_company_id());

        return back()->with('success', "Period [{$period->name}] locked.");
    }

    /**
     * Set as the active working period for the company.
     */
    public function setActive(Request $request, AccountingPeriod $period): RedirectResponse
    {
        AccountingPeriod::query()
            ->where('fiscal_year_id', $period->fiscal_year_id)
            ->update(['is_active' => false]);

        $period->update(['is_active' => true]);

        \App\Domain\Audit\Services\AuditLogger::log('period', 'set_active', null, $period->id, [], ['is_active' => true], current_company_id());

        return back()->with('success', "Period [{$period->name}] is now the active period.");
    }

    public function destroy(Request $request, AccountingPeriod $period): RedirectResponse
    {
        if ($period->is_active) {
            return back()->with('error', 'Cannot delete the active period.');
        }

        $period->delete();

        \App\Domain\Audit\Services\AuditLogger::log('period', 'delete', null, $period->id, $period->toArray(), [], current_company_id());

        return back()->with('success', 'Accounting period deleted.');
    }
}