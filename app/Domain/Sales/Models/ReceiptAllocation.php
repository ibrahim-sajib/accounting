<?php

namespace App\Domain\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptAllocation extends Model
{
    use HasFactory;

    protected $table = 'receipt_allocations';

    protected $fillable = [
        'receipt_id', 'sales_invoice_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }
}