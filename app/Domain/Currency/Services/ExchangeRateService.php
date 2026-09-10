<?php

namespace App\Domain\Currency\Services;

use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRate;
use Carbon\Carbon;

/**
 * Multi-currency conversion helper used by every module that deals
 * with foreign currency amounts.
 */
class ExchangeRateService
{
    /**
     * The rate for a currency on a given date (latest effective, or historical).
     */
    public function rateFor(Currency $currency, string|Carbon|null $date = null): ?ExchangeRate
    {
        if ($currency->isBaseCurrency()) {
            return new ExchangeRate(['rate' => 1, 'effective_date' => $date ?? Carbon::today()]);
        }

        $query = $currency->exchangeRates();

        if ($date) {
            $query->where('effective_date', '<=', $date);
        }

        return $query->orderByDesc('effective_date')->first();
    }

    /**
     * Convert an amount in the given currency to the base currency.
     */
    public function convertToBase(Currency $currency, float $amount, string|Carbon|null $date = null): float
    {
        if ($currency->isBaseCurrency()) {
            return (float) $amount;
        }

        $rate = $this->rateFor($currency, $date);

        if (! $rate) {
            throw new \RuntimeException("No exchange rate for [{$currency->code}] on [{$date ?? 'today'}].");
        }

        return (float) $amount * (float) $rate->rate;
    }

    public function convertFromBase(Currency $currency, float $baseAmount, string|Carbon|null $date = null): float
    {
        if ($currency->isBaseCurrency()) {
            return (float) $baseAmount;
        }

        $rate = $this->rateFor($currency, $date);

        if (! $rate || (float) $rate->rate <= 0) {
            throw new \RuntimeException("No valid exchange rate for [{$currency->code}].");
        }

        return (float) $baseAmount / (float) $rate->rate;
    }
}