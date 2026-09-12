<?php

namespace App\Domain\CashBank\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Currency\Models\Currency;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditFields;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'company_id',
        'branch_id',
        'account_name',
        'account_no',
        'bank_name',
        'branch_name',
        'currency_id',
        'gl_account_id',
        'is_active',
        'last_reconciled_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_reconciled_date' => 'date',
    ];

    public function glAccount()
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function cashOrBankTransactions()
    {
        return $this->hasMany(CashBankTransaction::class, 'bank_account_id');
    }
}