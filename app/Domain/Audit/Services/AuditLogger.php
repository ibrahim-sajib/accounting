<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Record a discrete auditable action (login, logout, approve, post, ...).
     */
    public static function log(
        string $module,
        string $action,
        ?string $recordType = null,
        ?int $recordId = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $companyId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'company_id' => $companyId ?? (Auth::check() ? session('active_company_id') : null),
            'user_id' => Auth::id(),
            'module' => $module,
            'action' => $action,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Attach an observer-friendly wrapper used by models with HasAuditFields.
     */
    public static function changed(Model|string $record, string $module, array $old = [], array $new = []): AuditLog
    {
        return static::log(
            $module,
            'update',
            get_class($record),
            $record->getKey(),
            $old,
            $new,
        );
    }
}