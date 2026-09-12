<?php

namespace App\Domain\Accounting\Models;

use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\PeriodStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingPeriod extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'accounting_periods';

    protected $fillable = [
        'fiscal_year_id', 'name', 'start_date', 'end_date', 'is_active', 'status',
        'closed_at', 'closed_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function statusValue(): PeriodStatus
    {
        return PeriodStatus::from($this->status);
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Open->value;
    }

    public function containsDate($date): bool
    {
        $date = is_string($date) ? \Illuminate\Support\Carbon::parse($date) : $date;

        return $this->start_date->lte($date) && $this->end_date->gte($date);
    }
}
