<?php

namespace App\Support\Enums;

enum AccountingBasis: string
{
    case Accrual = 'accrual';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Accrual => 'Accrual',
            self::Cash => 'Cash',
        };
    }
}
