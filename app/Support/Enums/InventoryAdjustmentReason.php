<?php

namespace App\Support\Enums;

enum InventoryAdjustmentReason: string
{
    case Damage = 'damage';
    case Theft = 'theft';
    case CountCorrection = 'count_correction';

    public function label(): string
    {
        return match ($this) {
            self::Damage => 'Damage',
            self::Theft => 'Theft',
            self::CountCorrection => 'Count Correction',
        };
    }
}