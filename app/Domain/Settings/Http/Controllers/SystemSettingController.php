<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Settings\Http\Requests\SystemSettingsRequest;
use App\Domain\Settings\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController
{
    protected array $groups = [
        'general' => 'General',
        'localization' => 'Localization',
        'numbering' => 'Numbering & Voucher',
        'notifications' => 'Notifications',
        'security' => 'Security',
        'document' => 'Document',
        'tax' => 'Tax',
        'accounting' => 'Accounting',
    ];

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $settings = SystemSetting::query()
            ->where('company_id', $companyId)
            ->orderBy('group')
            ->get();

        $grouped = $this->groups;

        foreach ($grouped as $key => $label) {
            $items = $settings->where('group', $key)->values();
            $grouped[$key] = [
                'label' => $label,
                'items' => $items->map(fn ($setting) => [
                    'key' => $setting->key,
                    'value' => $this->decodeValue($setting),
                    'type' => $setting->type,
                ]),
            ];
        }

        return Inertia::render('Settings/Index', [
            'groups' => $grouped,
            'activeTab' => $request->query('tab', 'general'),
        ]);
    }

    public function update(SystemSettingsRequest $request): RedirectResponse
    {
        $companyId = current_company_id();
        $payload = $request->validated()['settings'];

        foreach ($payload as $key => $value) {
            $setting = SystemSetting::query()
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->first();

            if (! $setting) {
                continue;
            }

            $setting->update([
                'value' => $this->encodeValue($value, $setting->type),
            ]);
        }

        \App\Domain\Audit\Services\AuditLogger::log('settings', 'update', null, $companyId, [], $payload, $companyId);

        return back()->with('success', 'Settings saved successfully.');
    }

    protected function decodeValue(SystemSetting $setting): mixed
    {
        return match ($setting->type) {
            'bool' => (bool) $setting->value,
            'int' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => (string) $setting->value,
        };
    }

    protected function encodeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => (string) (int) (bool) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}