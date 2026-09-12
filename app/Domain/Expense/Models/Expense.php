<?php

namespace App\Domain\Expense\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\Journal;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Party\Models\Supplier;
use App\Domain\Tax\Models\TaxRate;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\ExpensePaymentMethod;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditFields;

    protected $table = 'expenses';

    protected $fillable = [
        'company_id',
        'branch_id',
        'expense_no',
        'category_id',
        'payee',
        'expense_date',
        'amount',
        'tax_rate_id',
        'tax_amount',
        'payment_method',
        'cash_account_id',
        'bank_account_id',
        'supplier_id',
        'payable_account_id',
        'reference',
        'notes',
        'status',
        'journal_id',
        'is_recurring',
        'recurrence_frequency',
        'next_generation_date',
        'posted_at',
        'posted_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'tax_amount' => 'float',
        'expense_date' => 'date',
        'is_recurring' => 'boolean',
        'next_generation_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payableAccount()
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function total()
    {
        return round($this->amount + $this->tax_amount, 4);
    }

    public function paymentMethod(): ?ExpensePaymentMethod
    {
        return $this->payment_method ? ExpensePaymentMethod::tryFrom($this->payment_method) : null;
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted->value;
    }
}