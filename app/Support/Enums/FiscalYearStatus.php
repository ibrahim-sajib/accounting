<?php

namespace App\Support\Enums;

enum FiscalYearStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }
}
