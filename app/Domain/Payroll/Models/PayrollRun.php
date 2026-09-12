<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'payroll_runs';

    protected $fillable = [
        'company_id', 'branch_id', 'period_id', 'run_no', 'run_date',
        'status', 'total_gross', 'total_deductions', 'total_net',
        'journal_id', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'run_date' => 'date',
        'total_gross' => 'decimal:4',
        'total_deductions' => 'decimal:4',
        'total_net' => 'decimal:4',
        'posted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function lines()
    {
        return $this->hasMany(PayrollRunLine::class, 'payroll_run_id')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(SalaryPayment::class, 'payroll_run_id')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === TransactionStatus::Draft->value;
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted->value;
    }

    public function paidTotal(): float
    {
        return round($this->payments()->sum('amount'), 4);
    }

    public function remainingPayable(): float
    {
        return round(max(0, $this->total_net - $this->paidTotal()), 4);
    }

    public function paidState(): string
    {
        if ((float) $this->total_net <= 0) {
            return 'paid';
        }

        $paid = $this->paidTotal();

        if ($paid >= $this->total_net - 0.0001) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }
}