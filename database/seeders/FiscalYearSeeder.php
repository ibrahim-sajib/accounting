<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Company\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::query()->get();

        foreach ($companies as $company) {
            $fiscalYearStart = $company->fiscal_year_start
                ? Carbon::parse($company->fiscal_year_start)
                : now()->startOfYear();

            $firstYear = $fiscalYearStart->copy()->startOfYear();

            foreach (range(0, 1) as $offset) {
                $start = $firstYear->copy()->addYears($offset)->startOfYear();
                $end = $start->copy()->addYear()->subDay();

                $name = $start->format('Y').'-'.$end->format('Y');

                $fiscalYear = FiscalYear::query()->updateOrCreate(
                    ['company_id' => $company->id, 'name' => $name],
                    [
                        'start_date' => $start,
                        'end_date' => $end,
                        'is_active' => $offset === 0,
                        'status' => 'open',
                    ]
                );

                $this->ensurePeriods($fiscalYear);
            }
        }
    }

    protected function ensurePeriods(FiscalYear $fiscalYear): void
    {
        $cursor = $fiscalYear->start_date->copy()->startOfMonth();

        while ($cursor->lte($fiscalYear->end_date)) {
            $periodEnd = $cursor->copy()->endOfMonth()->min($fiscalYear->end_date);

            AccountingPeriod::query()->updateOrCreate(
                ['fiscal_year_id' => $fiscalYear->id, 'start_date' => $cursor->copy()],
                [
                    'name' => $cursor->format('Y-m'),
                    'end_date' => $periodEnd,
                    'is_active' => $cursor->isSameMonth(now()),
                    'status' => 'open',
                ]
            );

            $cursor->addMonth();
        }
    }
}