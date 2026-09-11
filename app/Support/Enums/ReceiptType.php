<?php

namespace App\Support\Enums;

enum ReceiptType: string
{
    case Receipt = 'receipt';
    case Advance = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Advance => 'Advance',
        };
    }
}