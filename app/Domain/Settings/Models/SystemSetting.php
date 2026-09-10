<?php

namespace App\Domain\Settings\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'company_id', 'group', 'key', 'value', 'type',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scoped accessor: group-key domain store, e.g. setting('localization.date_format').
     */
    public static function setting(string $key, ?int $companyId = null, mixed $default = null): mixed
    {
        [$group, $name] = array_pad(explode('.', $key, 2), 2, $key);

        $setting = static::query()
            ->where('group', $group)
            ->where('key', $name)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if (! $setting) {
            return $default;
        }

        return (string) $setting->value;
    }
}