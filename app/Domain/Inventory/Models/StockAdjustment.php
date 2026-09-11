<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Accounting\Models\Journal;
use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Warehouse\Models\Warehouse;
use App\Models\User;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'stock_adjustments';

    protected $fillable = [
        'company_id', 'branch_id', 'adjustment_no', 'adjustment_date',
        'warehouse_id', 'reason', 'memo', 'status', 'total_value',
        'posted_at', 'posted_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'total_value' => 'decimal:4',
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(StockAdjustmentLine::class)->orderBy('id');
    }

    public function journal()
    {
        return $this->hasOne(Journal::class, 'source_id')
            ->where('source_type', 'stock_adjustment');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted->value;
    }

    public function isDraft(): bool
    {
        return $this->status === TransactionStatus::Draft->value;
    }
}