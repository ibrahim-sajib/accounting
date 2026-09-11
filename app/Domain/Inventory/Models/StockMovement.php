<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Product\Models\Product;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    protected $fillable = [
        'company_id', 'warehouse_id', 'product_id', 'movement_type',
        'quantity', 'unit_cost', 'line_value',
        'source_type', 'source_id', 'reference', 'memo',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'line_value' => 'decimal:4',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}