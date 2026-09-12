<?php

namespace App\Domain\Budget\Models;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\BudgetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'budgets';

    protected $fillable = [
        'company_id', 'fiscal_year_id', 'name', 'status', 'notes',
        'created_by', 'updated_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function lines()
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function statusValue(): BudgetStatus
    {
        return BudgetStatus::from($this->status);
    }

    public function totalBudgeted(): float
    {
        return (float) $this->lines()->sum('budgeted_amount');
    }
}