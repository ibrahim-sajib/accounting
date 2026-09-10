<?php

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    protected array $currencies = [
        ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'decimal_places' => 2, 'is_base' => true],
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => false],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false],
        ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2, 'is_base' => false],
        ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2, 'is_base' => false],
        ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'decimal_places' => 2, 'is_base' => false],
    ];

    public function run(): void
    {
        $companies = Company::query()->get();

        foreach ($companies as $company) {
            foreach ($this->currencies as $currency) {
                Currency::query()->updateOrCreate(
                    ['company_id' => $company->id, 'code' => $currency['code']],
                    $currency
                );
            }
        }
    }
}