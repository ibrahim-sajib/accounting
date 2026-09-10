<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Domain\Accounting\Http\Requests\FiscalYearRequest;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Support\Enums\FiscalYearStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearController
{
    public function index(Request $request): Response
    {
        $fiscalYears = FiscalYear::query()
            ->where('company_id', current_company_id())
            ->withCount('periods')
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('FiscalYears/Index', [
            'fiscalYears' => $fiscalYears,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FiscalYears/Create');
    }

    public function store(FiscalYearRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $companyId = current_company_id();

        // Only one active fiscal year per company at a time.
        if (! empty($data['is_active'])) {
            FiscalYear::query()->where('company_id', $companyId)->update(['is_active' => false]);
        }

        $fiscalYear = FiscalYear::query()->create($data + ['company_id' => $companyId]);

        $this->autoGeneratePeriods($fiscalYear);

        \App\Domain\Audit\Services\AuditLogger::log('fiscal_year', 'create', null, $fiscalYear->id, [], $fiscalYear->toArray(), $companyId);

        return redirect()
            ->route('fiscal-years.index')
            ->with('success', 'Fiscal year created with monthly periods.');
    }

    public function edit(FiscalYear $fiscalYear): Response
    {
        return Inertia::render('FiscalYears/Edit', [
            'fiscalYear' => $fiscalYear,
            'statusOptions' => enum_options(FiscalYearStatus::class),
        ]);
    }

    public function update(FiscalYearRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $old = $fiscalYear->toArray();
        $data = $request->validated();

        if (! empty($data['is_active'])) {
            FiscalYear::query()->where('company_id', $fiscalYear->company_id)->update(['is_active' => false]);
        }

        $fiscalYear->update($data);

        \App\Domain\Audit\Services\AuditLogger::log('fiscal_year', 'update', null, $fiscalYear->id, $old, $fiscalYear->toArray(), $fiscalYear->company_id);

        return redirect()
            ->route('fiscal-years.index')
            ->with('success', 'Fiscal year updated successfully.');
    }

    public function close(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->is_active) {
            return back()->with('error', 'Close the active fiscal year after creating a new one first.');
        }

        $fiscalYear->update(['status' => FiscalYearStatus::Closed->value]);

        $fiscalYear->periods()
            ->where('status', \App\Support\Enums\PeriodStatus::Open->value)
            ->update(['status' => \App\Support\Enums\PeriodStatus::Closed->value]);

        \App\Domain\Audit\Services\AuditLogger::log('fiscal_year', 'close', null, $fiscalYear->id, [], ['status' => 'closed'], $fiscalYear->company_id);

        return back()->with('success', 'Fiscal year closed.');
    }

    public function reopen(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $fiscalYear->update(['status' => FiscalYearStatus::Open->value]);

        \App\Domain\Audit\Services\AuditLogger::log('fiscal_year', 'reopen', null, $fiscalYear->id, [], ['status' => 'open'], $fiscalYear->company_id);

        return back()->with('success', 'Fiscal year reopened.');
    }

    public function destroy(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $fiscalYear->delete();

        \App\Domain\Audit\Services\AuditLogger::log('fiscal_year', 'delete', null, $fiscalYear->id, $fiscalYear->toArray(), [], $fiscalYear->company_id);

        return redirect()
            ->route('fiscal-years.index')
            ->with('success', 'Fiscal year deleted.');
    }

    protected function autoGeneratePeriods(FiscalYear $fiscalYear): void
    {
        $cursor = Carbon::create($fiscalYear->start_date)->startOfMonth();
        $end = Carbon::create($fiscalYear->end_date);

        while ($cursor->lte($end)) {
            $periodEnd = $cursor->copy()->endOfMonth()->min($end);

            AccountingPeriod::query()->create([
                'fiscal_year_id' => $fiscalYear->id,
                'name' => $cursor->format('Y-m'),
                'start_date' => $cursor->copy(),
                'end_date' => $periodEnd,
                'is_active' => $cursor->isSameMonth(now()),
                'status' => 'open',
            ]);

            $cursor->addMonth();
        }
    }
}