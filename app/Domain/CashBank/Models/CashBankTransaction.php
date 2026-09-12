<?php

namespace App\Domain\CashBank\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\Journal;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankTransaction extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditFields;

    protected $table = 'cash_bank_transactions';

    protected $fillable = [
        'company_id',
        'branch_id',
        'transaction_no',
        'transaction_type',
        'transaction_date',
        'amount',
        'cash_account_id',
        'bank_account_id',
        'to_bank_account_id',
        'counter_account_id',
        'reference',
        'description',
        'journal_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'transaction_date' => 'date',
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function toBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id');
    }

    public function counterAccount()
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }
}