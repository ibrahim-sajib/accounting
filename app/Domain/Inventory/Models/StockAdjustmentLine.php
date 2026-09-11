<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentLine extends Model
{
    use HasFactory;

    protected $table = 'stock_adjustment_lines';

    protected $fillable = [
        'company_id', 'stock_adjustment_id', 'product_id',
        'system_qty', 'counted_qty', 'quantity_delta',
        'unit_cost', 'line_value',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'system_qty' => 'decimal:6',
        'counted_qty' => 'decimal:6',
        'quantity_delta' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'line_value' => 'decimal:4',
    ];

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}