<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\FiscalYearStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'fiscal_years';

    protected $fillable = [
        'company_id', 'name', 'start_date', 'end_date', 'is_active', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function periods()
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    public function statusValue(): FiscalYearStatus
    {
        return FiscalYearStatus::from($this->status);
    }

    public function isOpen(): bool
    {
        return $this->status === FiscalYearStatus::Open->value;
    }

    public function canPostInDate($date): bool
    {
        $date = is_string($date) ? \Illuminate\Support\Carbon::parse($date) : $date;

        return $this->start_date->lte($date) && $this->end_date->gte($date);
    }
}
