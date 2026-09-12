<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Accounting\Models\Journal;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryPayment extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'salary_payments';

    protected $fillable = [
        'company_id', 'payroll_run_id', 'payment_method', 'cash_account_id',
        'bank_account_id', 'amount', 'payment_date', 'payment_no',
        'journal_id', 'paid_at', 'paid_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'payment_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}