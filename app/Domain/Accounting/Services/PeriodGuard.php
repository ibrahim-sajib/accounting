<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Support\Enums\PeriodStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Central guard for posting into accounting periods.
 *
 * Every module that posts a journal entry calls canPost() / ensureOpen()
 * before committing, so that the closed/locked period rule can never be
 * bypassed by a single module.
 */
class PeriodGuard
{
    /**
     * Find the accounting period a given date belongs to within the company.
     */
    public function findPeriodForDate(string|Carbon $date, int $companyId): ?AccountingPeriod
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * Resolve the default/active period for the company.
     */
    public function activePeriod(int $companyId): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))
            ->where('is_active', true)
            ->first();
    }

    /**
     * True when transactions may be posted in the period for the given date.
     */
    public function canPost(string|Carbon $date, int $companyId): bool
    {
        $period = $this->findPeriodForDate($date, $companyId);

        return $period !== null && $period->status === PeriodStatus::Open->value;
    }

    public function ensureOpen(string|Carbon $date, int $companyId): AccountingPeriod
    {
        $period = $this->findPeriodForDate($date, $companyId);

        if (! $period) {
            throw new \App\Domain\Accounting\Exceptions\ClosedPeriodException(
                "No accounting period exists for date [{$date}]."
            );
        }

        if ($period->status !== PeriodStatus::Open->value) {
            throw new \App\Domain\Accounting\Exceptions\ClosedPeriodException(
                "The period [{$period->name}] is [{$period->status}] and cannot accept postings."
            );
        }

        return $period;
    }

    /**
     * All monthly periods of a fiscal year grouped for reporting.
     */
    public function periodsOfFiscalYear(FiscalYear $fiscalYear): Collection
    {
        return $fiscalYear->periods()->orderBy('start_date')->get();
    }
}