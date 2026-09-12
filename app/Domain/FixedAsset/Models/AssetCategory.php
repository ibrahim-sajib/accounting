<?php

namespace App\Domain\FixedAsset\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\DepreciationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCategory extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'asset_categories';

    protected $fillable = [
        'company_id', 'name', 'asset_account_id', 'depreciation_expense_account_id',
        'accumulated_depreciation_account_id', 'default_method', 'default_useful_life_months',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_useful_life_months' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assets()
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    public function assetAccount()
    {
        return $this->belongsTo(\App\Domain\Accounting\Models\Account::class, 'asset_account_id');
    }

    public function depreciationExpenseAccount()
    {
        return $this->belongsTo(\App\Domain\Accounting\Models\Account::class, 'depreciation_expense_account_id');
    }

    public function accumulatedDepreciationAccount()
    {
        return $this->belongsTo(\App\Domain\Accounting\Models\Account::class, 'accumulated_depreciation_account_id');
    }
}