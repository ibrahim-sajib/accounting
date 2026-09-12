<?php

namespace App\Domain\CashBank\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditFields;

    protected $table = 'cash_accounts';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'gl_account_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function glAccount()
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function cashOrBankTransactions()
    {
        return $this->hasMany(CashBankTransaction::class, 'cash_account_id');
    }
}