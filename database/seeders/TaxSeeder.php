<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default tax types and rates for every company, wiring the rates
 * to the seeded chart of accounts (VAT input/output, WHT payable).
 * Idempotent via firstOrCreate on (company_id, name).
 */
class TaxSeeder extends Seeder
{
    protected array $taxTypes = [
        'VAT' => [
            'rates' => [
                ['name' => 'Standard Rate 15%', 'rate_percent' => 15, 'is_inclusive' => true, 'input' => '1151', 'output' => '2121'],
                ['name' => 'Reduced Rate 7.5%', 'rate_percent' => 7.5, 'is_inclusive' => true, 'input' => '1151', 'output' => '2121'],
                ['name' => 'Zero-Rated', 'rate_percent' => 0, 'is_inclusive' => false, 'input' => '1151', 'output' => '2121'],
            ],
        ],
        'Withholding Tax' => [
            'rates' => [
                ['name' => 'WHT 3%', 'rate_percent' => 3, 'is_inclusive' => false, 'input' => null, 'output' => '2131'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (Company::all() as $company) {
            $inputVat = Account::where('company_id', $company->id)->where('code', '1151')->value('id');
            $outputVat = Account::where('company_id', $company->id)->where('code', '2121')->value('id');
            $whtPayable = Account::where('company_id', $company->id)->where('code', '2131')->value('id');

            DB::transaction(function () use ($company, $inputVat, $outputVat, $whtPayable) {
                foreach ($this->taxTypes as $typeName => $config) {
                    $taxType = TaxType::withTrashed()->firstOrCreate(
                        ['company_id' => $company->id, 'name' => $typeName]
                    );
                    if ($taxType->trashed()) {
                        $taxType->restore();
                    }

                    foreach ($config['rates'] as $rate) {
                        $taxRate = TaxRate::withTrashed()->firstOrCreate(
                            [
                                'company_id' => $company->id,
                                'tax_type_id' => $taxType->id,
                                'name' => $rate['name'],
                                'effective_date' => now()->startOfYear()->toDateString(),
                            ],
                            [
                                'rate_percent' => $rate['rate_percent'],
                                'is_inclusive' => $rate['is_inclusive'],
                                'input_account_id' => $rate['input'] === null ? null : ($rate['input'] === '1151' ? $inputVat : $outputVat),
                                'output_account_id' => $rate['output'] === '2121' ? $outputVat : ($rate['output'] === '2131' ? $whtPayable : null),
                            ]
                        );
                        if ($taxRate->trashed()) {
                            $taxRate->restore();
                        }
                    }
                }
            });
        }
    }
}