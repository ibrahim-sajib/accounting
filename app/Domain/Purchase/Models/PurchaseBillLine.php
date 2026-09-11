<?php

namespace App\Domain\Purchase\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Tax\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseBillLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_bill_lines';

    protected $fillable = [
        'purchase_bill_id', 'product_id', 'description', 'quantity', 'unit_cost',
        'discount_amount', 'tax_rate_id', 'tax_rate_percent', 'tax_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate_percent' => 'decimal:6',
        'tax_amount' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function bill()
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }
}