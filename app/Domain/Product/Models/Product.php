<?php

namespace App\Domain\Product\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Domain\Tax\Models\TaxRate;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'products';

    protected $fillable = [
        'company_id', 'sku', 'name', 'type', 'category_id', 'unit_id',
        'purchase_price', 'sales_price', 'tax_rate_id',
        'inventory_account_id', 'sales_account_id', 'purchase_account_id', 'cogs_account_id',
        'track_inventory', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'sales_price' => 'decimal:4',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function inventoryAccount()
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function salesAccount()
    {
        return $this->belongsTo(Account::class, 'sales_account_id');
    }

    public function purchaseAccount()
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    public function cogsAccount()
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function typeValue(): ProductType
    {
        return ProductType::from($this->type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}