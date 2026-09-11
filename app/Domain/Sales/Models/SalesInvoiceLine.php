<?php

namespace App\Domain\Sales\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Tax\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceLine extends Model
{
    use HasFactory;

    protected $table = 'sales_invoice_lines';

    protected $fillable = [
        'sales_invoice_id', 'product_id', 'description', 'quantity', 'unit_price',
        'discount_amount', 'tax_rate_id', 'tax_rate_percent', 'tax_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_price' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate_percent' => 'decimal:6',
        'tax_amount' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
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