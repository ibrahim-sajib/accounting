<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransferLine extends Model
{
    use HasFactory;

    protected $table = 'stock_transfer_lines';

    protected $fillable = [
        'company_id', 'stock_transfer_id', 'product_id',
        'quantity', 'unit_cost', 'line_value',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'line_value' => 'decimal:4',
    ];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}