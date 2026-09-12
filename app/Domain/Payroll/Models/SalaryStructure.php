<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryStructure extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'salary_structures';

    protected $fillable = [
        'company_id', 'employee_id', 'basic', 'house_rent_allowance',
        'medical_allowance', 'travel_allowance', 'other_allowance',
        'income_tax_deduction', 'provident_fund_deduction', 'other_deduction',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'basic' => 'decimal:4',
        'house_rent_allowance' => 'decimal:4',
        'medical_allowance' => 'decimal:4',
        'travel_allowance' => 'decimal:4',
        'other_allowance' => 'decimal:4',
        'income_tax_deduction' => 'decimal:4',
        'provident_fund_deduction' => 'decimal:4',
        'other_deduction' => 'decimal:4',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function allowancesTotal(): float
    {
        return round(
            $this->house_rent_allowance
            + $this->medical_allowance
            + $this->travel_allowance
            + $this->other_allowance,
            4
        );
    }

    public function deductionsTotal(): float
    {
        return round(
            $this->income_tax_deduction
            + $this->provident_fund_deduction
            + $this->other_deduction,
            4
        );
    }

    public function grossPay(): float
    {
        return round($this->basic + $this->allowancesTotal(), 4);
    }

    public function netPay(): float
    {
        return round($this->grossPay() - $this->deductionsTotal(), 4);
    }
}