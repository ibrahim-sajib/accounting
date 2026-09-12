<?php

namespace App\Support\Enums;

enum BudgetStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}