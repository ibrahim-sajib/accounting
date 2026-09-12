<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Models\User;
use App\Support\Enums\PeriodStatus;
use Illuminate\Support\Carbon;

class AccountingPeriodService
{
    /**
     * Periods may only be managed (closed/locked/reopened/deleted) inside the
     * active company — a company-id mismatch is a hard 403, never a 404.
     */
    public static function assertCompanyPeriod(AccountingPeriod $period): void
    {
        $period->loadMissing('fiscalYear');

        abort_unless((int) ($period->fiscalYear->company_id ?? 0) === (int) current_company_id(), 403);
    }

    /**
     * Close a period: blocks all further posting via JournalPostingService
     * (posting already refuses closed periods). Sequential — every earlier
     * period in the fiscal year must already be closed/locked.
     */
    public static function close(AccountingPeriod $period, User $user, int $companyId): void
    {
        self::assertCompanyPeriod($period);

        if (! $period->isOpen()) {
            throw new \DomainException("Period [{$period->name}] is not open.");
        }

        $openEarlier = self::siblings($period)
            ->where('start_date', '<', $period->start_date)
            ->whereIn('status', [PeriodStatus::Open->value])
            ->exists();

        if ($openEarlier) {
            throw new \DomainException('Close earlier periods first: periods must be closed in sequence.');
        }

        $period->update([
            'status' => PeriodStatus::Closed->value,
            'closed_at' => Carbon::now(),
            'closed_by' => $user->id,
        ]);

        \App\Domain\Audit\Services\AuditLogger::log('period', 'close', null, $period->id, ['status' => 'open'], ['status' => 'closed'], $companyId);
    }

    /**
     * Reopen a closed period — refused when a LATER period has been locked
     * (locked periods are permanent; reopening would open a hole).
     */
    public static function reopen(AccountingPeriod $period, User $user, int $companyId): void
    {
        self::assertCompanyPeriod($period);

        if ($period->statusValue() === PeriodStatus::Open) {
            throw new \DomainException("Period [{$period->name}] is already open.");
        }

        if ($period->statusValue() === PeriodStatus::Locked) {
            throw new \DomainException("Period [{$period->name}] is locked and cannot be reopened.");
        }

        $lockedLater = self::siblings($period)
            ->where('start_date', '>', $period->start_date)
            ->where('status', PeriodStatus::Locked->value)
            ->exists();

        if ($lockedLater) {
            throw new \DomainException('A later period is locked. Reopening this period would leave a gap.');
        }

        $period->update([
            'status' => PeriodStatus::Open->value,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        \App\Domain\Audit\Services\AuditLogger::log('period', 'reopen', $period::class, $period->id, ['status' => 'closed'], ['status' => 'open'], $companyId);
    }

    /**
     * Lock a period permanently (read-only). Sequential like close.
     */
    public static function lock(AccountingPeriod $period, User $user, int $companyId): void
    {
        self::assertCompanyPeriod($period);

        if ($period->statusValue() === PeriodStatus::Locked) {
            throw new \DomainException("Period [{$period->name}] is already locked.");
        }

        $openEarlier = self::siblings($period)
            ->where('start_date', '<', $period->start_date)
            ->whereIn('status', [PeriodStatus::Open->value])
            ->exists();

        if ($openEarlier) {
            throw new \DomainException('Close earlier periods first: periods must be locked in sequence.');
        }

        $period->update([
            'status' => PeriodStatus::Locked->value,
            'closed_at' => Carbon::now(),
            'closed_by' => $user->id,
        ]);

        \App\Domain\Audit\Services\AuditLogger::log('period', 'lock', null, $period->id, ['status' => $period->status], ['status' => 'locked'], $companyId);
    }

    public static function assertCanBeActive(AccountingPeriod $period): void
    {
        self::assertCompanyPeriod($period);

        abort_unless($period->statusValue() !== PeriodStatus::Locked && $period->statusValue() !== PeriodStatus::Closed, 422, 'Only open periods can be set as active.');
    }

    public static function assertCanBeDeleted(AccountingPeriod $period): void
    {
        self::assertCompanyPeriod($period);

        abort_if($period->is_active, 422, 'Cannot delete the active period.');
        abort_if($period->statusValue() === PeriodStatus::Closed || $period->statusValue() === PeriodStatus::Locked, 422, 'Cannot delete a closed or locked period.');
    }

    private static function siblings(AccountingPeriod $period): \Illuminate\Database\Eloquent\Builder
    {
        return AccountingPeriod::query()
            ->where('id', '!=', $period->id)
            ->where('fiscal_year_id', $period->fiscal_year_id);
    }
}