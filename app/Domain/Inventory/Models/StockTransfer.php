<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Warehouse\Models\Warehouse;
use App\Models\User;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'stock_transfers';

    protected $fillable = [
        'company_id', 'branch_id', 'transfer_no', 'transfer_date',
        'from_warehouse_id', 'to_warehouse_id', 'reference', 'memo',
        'status', 'posted_at', 'posted_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
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

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function lines()
    {
        return $this->hasMany(StockTransferLine::class)->orderBy('id');
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