<?php

namespace App\Domain\Expense\Models;

use App\Domain\Accounting\Models\Account;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditFields;

    protected $table = 'expense_categories';

    protected $fillable = [
        'company_id',
        'name',
        'expense_account_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id');
    }
}