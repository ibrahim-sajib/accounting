<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'employees';

    protected $fillable = [
        'company_id', 'branch_id', 'department_id', 'designation_id',
        'name', 'email', 'phone', 'join_date', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'join_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function salaryStructure()
    {
        return $this->hasOne(SalaryStructure::class)
            ->withTrashed()
            ->orderBy('id');
    }

    public function payrollRunLines()
    {
        return $this->hasMany(PayrollRunLine::class);
    }

    public function referencedByPayroll(): bool
    {
        return $this->payrollRunLines()->exists();
    }
}