<?php

namespace App\Domain\Sales\Models;

use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Party\Models\Customer;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'sales_invoices';

    protected $fillable = [
        'company_id', 'branch_id', 'customer_id', 'invoice_no', 'invoice_date',
        'due_date', 'reference', 'subtotal', 'discount_amount', 'tax_amount',
        'total', 'amount_paid', 'notes', 'status', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total' => 'decimal:4',
        'amount_paid' => 'decimal:4',
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

    public function lines()
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('id');
    }

    public function receipts()
    {
        return $this->belongsToMany(Receipt::class, 'receipt_allocations', 'sales_invoice_id', 'receipt_id')
            ->withPivot(['amount'])->with('account')->with('journal');
    }

    public function journal()
    {
        return $this->hasOne(\App\Domain\Accounting\Models\Journal::class, 'source_id')
            ->where('source_type', 'sales_invoice');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted->value;
    }

    public function isDraft(): bool
    {
        return $this->status === TransactionStatus::Draft->value;
    }

    /** unpaid | partial | paid — derived from amount_paid, only meaningful once posted. */
    public function paidState(): string
    {
        if ($this->status !== TransactionStatus::Posted->value) {
            return 'draft';
        }

        $total = (float) $this->total;
        $paid = (float) $this->amount_paid;

        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid >= $total - 0.0001) {
            return 'paid';
        }

        return 'partial';
    }

    public function isOverdue(): bool
    {
        if (! $this->isPosted() || $this->paidState() === 'paid') {
            return false;
        }

        return $this->due_date && $this->due_date->lt(today());
    }

    public function balanceDue(): float
    {
        return max((float) $this->total - (float) $this->amount_paid, 0);
    }
}