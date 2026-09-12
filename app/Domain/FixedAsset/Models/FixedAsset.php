<?php

namespace App\Domain\FixedAsset\Models;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Company\Models\Company;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Party\Models\Supplier;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\AssetStatus;
use App\Support\Enums\DepreciationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'fixed_assets';

    protected $fillable = [
        'company_id', 'branch_id', 'category_id', 'asset_code', 'name',
        'acquisition_date', 'acquisition_cost', 'useful_life_months', 'method',
        'location', 'status', 'acquisition_method', 'cash_account_id',
        'bank_account_id', 'supplier_id', 'payable_account_id', 'journal_id',
        'posted_at', 'posted_by', 'disposed_at', 'disposed_by',
        'disposal_date', 'disposal_proceeds',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:4',
        'disposal_proceeds' => 'decimal:4',
        'useful_life_months' => 'integer',
        'posted_at' => 'datetime',
        'disposed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function depreciationEntries()
    {
        return $this->hasMany(DepreciationEntry::class)->orderBy('id');
    }

    public function disposal()
    {
        return $this->hasOne(AssetDisposal::class)->latestOfMany();
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function statusValue(): AssetStatus
    {
        return AssetStatus::from($this->status);
    }

    public function methodValue(): DepreciationMethod
    {
        return DepreciationMethod::from($this->method);
    }

    public function isDraft(): bool
    {
        return $this->status === AssetStatus::Draft->value;
    }

    public function isActive(): bool
    {
        return $this->status === AssetStatus::Active->value;
    }

    public function isDisposed(): bool
    {
        return $this->status === AssetStatus::Disposed->value;
    }

    public function accumulatedDepreciationAmount(): float
    {
        return $this->depreciationEntries()->sum('monthly_amount');
    }

    public function bookValue(): float
    {
        return max(0, $this->acquisition_cost - $this->accumulatedDepreciationAmount());
    }

    public function depreciationBookedForPeriod(int $periodId): bool
    {
        return $this->depreciationEntries()->where('period_id', $periodId)->exists();
    }
}