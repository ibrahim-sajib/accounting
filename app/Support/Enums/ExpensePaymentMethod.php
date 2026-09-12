<?php

namespace App\Support\Enums;

enum ExpensePaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Payable = 'payable';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::Payable => 'Payable',
        };
    }

    public function isPayable(): bool
    {
        return $this === self::Payable;
    }
}