<?php

namespace App\Support\Enums;

enum SalaryPaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
        };
    }
}