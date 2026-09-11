<?php

namespace App\Domain\Purchase\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Party\Models\Supplier;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'supplier_payments';

    protected $fillable = [
        'company_id', 'branch_id', 'supplier_id', 'payment_no', 'payment_date',
        'account_id', 'reference', 'memo', 'amount', 'status', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function journal()
    {
        return $this->hasOne(\App\Domain\Accounting\Models\Journal::class, 'source_id')
            ->where('source_type', 'payment');
    }
}