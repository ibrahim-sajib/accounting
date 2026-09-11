<?php

namespace App\Domain\Sales\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Party\Models\Customer;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'receipts';

    protected $fillable = [
        'company_id', 'branch_id', 'customer_id', 'type', 'receipt_no', 'receipt_date',
        'account_id', 'reference', 'memo', 'amount', 'status', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'amount' => 'decimal:4',
        'posted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function allocations()
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function journal()
    {
        return $this->hasOne(\App\Domain\Accounting\Models\Journal::class, 'source_id')
            ->whereIn('source_type', ['receipt', 'receipt_application']);
    }

    public function isAdvance(): bool
    {
        return $this->type === \App\Support\Enums\ReceiptType::Advance->value;
    }

    public function appliedAmount(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function advanceBalance(): float
    {
        return max((float) $this->amount - (float) $this->appliedAmount(), 0);
    }
}