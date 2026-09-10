<?php

namespace App\Domain\Currency\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'currencies';

    protected $fillable = [
        'company_id', 'code', 'name', 'symbol', 'decimal_places', 'is_base', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function exchangeRates()
    {
        return $this->hasMany(ExchangeRate::class);
    }

    public function isBaseCurrency(): bool
    {
        return (bool) $this->is_base;
    }

    /**
     * Latest effective exchange rate against the base currency.
     */
    public function latestRate(?Carbon $asOf = null): ?ExchangeRate
    {
        return $this->exchangeRates()
            ->when($asOf, fn ($q) => $q->where('effective_date', '<=', $asOf))
            ->orderByDesc('effective_date')
            ->first();
    }
}