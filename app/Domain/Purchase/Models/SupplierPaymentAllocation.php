<?php

namespace App\Domain\Purchase\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierPaymentAllocation extends Model
{
    use HasFactory;

    protected $table = 'supplier_payment_allocations';

    protected $fillable = [
        'supplier_payment_id', 'purchase_bill_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function bill()
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }
}