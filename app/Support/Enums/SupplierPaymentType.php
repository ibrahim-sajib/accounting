<?php

namespace App\Support\Enums;

enum SupplierPaymentType: string
{
    case Payment = 'payment';
    case Advance = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Payment',
            self::Advance => 'Advance',
        };
    }
}