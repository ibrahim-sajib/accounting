<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRunLine extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'payroll_run_lines';

    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id', 'gross_pay',
        'allowances_total', 'deductions_total', 'net_pay',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'gross_pay' => 'decimal:4',
        'allowances_total' => 'decimal:4',
        'deductions_total' => 'decimal:4',
        'net_pay' => 'decimal:4',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}