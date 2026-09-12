<?php

namespace App\Domain\Audit\Http\Controllers;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $query = AuditLog::query()
            ->with('user:id,name')
            ->where('company_id', $companyId);

        if ($module = $request->string('module')->trim()->toString()) {
            $query->where('module', $module);
        }

        if ($action = $request->string('action')->trim()->toString()) {
            $query->where('action', $action);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('record_type', 'like', "%{$search}%")
                ->where('ip_address', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")));
        }

        if ($from = $request->string('from')->trim()->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->string('to')->trim()->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $logs->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'module' => $log->module,
            'action' => $log->action,
            'user' => $log->user?->name,
            'record_type' => $log->record_type ? class_basename($log->record_type) : null,
            'record_id' => $log->record_id,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toISOString(),
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'diff' => $this->diff($log),
        ]);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['module', 'action', 'search', 'from', 'to']),
            'modules' => AuditLog::query()
                ->where('company_id', $companyId)
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'actions' => AuditLog::query()
                ->where('company_id', $companyId)
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }

    private function diff(AuditLog $log): array
    {
        $old = $log->old_values;
        $new = $log->new_values;

        if (! is_array($old) || ! is_array($new)) {
            return [];
        }

        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $keys = array_diff($keys, ['created_at', 'updated_at', 'deleted_at']);

        $rows = [];

        foreach ($keys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal === $newVal) {
                continue;
            }

            $rows[] = [
                'field' => $key,
                'old' => $this->shorten($oldVal),
                'new' => $this->shorten($newVal),
            ];

            if (count($rows) >= 8) {
                break;
            }
        }

        return $rows;
    }

    private function shorten(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $str = (string) $value;

        return strlen($str) > 120 ? substr($str, 0, 120).'…' : $str;
    }
}