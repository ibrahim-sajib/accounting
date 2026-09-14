<?php

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Seeds default product categories and units of measure for every company.
 * Idempotent via firstOrCreate on (company_id, name).
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Company::all() as $company) {
            foreach (['Goods', 'Services', 'Raw Materials'] as $category) {
                $pc = ProductCategory::withTrashed()->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $category],
                    ['created_by' => null, 'updated_by' => null]
                );
                if ($pc->trashed()) {
                    $pc->restore();
                }
            }

            foreach ([
                ['name' => 'Piece', 'symbol' => 'pc'],
                ['name' => 'Kilogram', 'symbol' => 'kg'],
                ['name' => 'Liter', 'symbol' => 'L'],
                ['name' => 'Box', 'symbol' => 'bx'],
                ['name' => 'Hour', 'symbol' => 'hr'],
            ] as $unit) {
                $u = Unit::withTrashed()->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $unit['name']],
                    $unit + ['created_by' => null, 'updated_by' => null]
                );
                if ($u->trashed()) {
                    $u->restore();
                }
            }
        }
    }
}