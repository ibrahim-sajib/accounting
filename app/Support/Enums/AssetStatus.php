<?php

namespace App\Support\Enums;

enum AssetStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Disposed => 'Disposed',
        };
    }
}