<?php

namespace App\Support\Enums;

enum CurrencyDecimalPlaces: int
{
    case Zero = 0;
    case Two = 2;
    case Three = 3;
    case Four = 4;

    public function label(): int
    {
        return match ($this) {
            self::Zero => 0,
            self::Two => 2,
            self::Three => 3,
            self::Four => 4,
        };
    }
}
