<?php

namespace App\Console\Commands;

use App\Domain\Company\Models\Company;
use App\Domain\Tenant\Services\TenantManager;
use Illuminate\Console\Command;

class TenantProvisionCommand extends Command
{
    protected $signature = 'tenant:provision {company? : Company ID; omit to provision every company}';

    protected $description = 'Create, migrate and seed a dedicated database for each company.';

    public function handle(TenantManager $tenancy): int
    {
        if (! $tenancy->enabled()) {
            $this->warn('Tenancy is disabled (not on the mysql driver or TENANCY_ENABLED=false). Nothing to do.');

            return self::SUCCESS;
        }

        $companies = $this->argument('company') !== null
            ? collect([Company::query()->findOrFail((int) $this->argument('company'))])
            : Company::query()->get();

        if ($companies->isEmpty()) {
            $this->warn('No companies found.');

            return self::SUCCESS;
        }

        $provisioned = 0;
        $skipped = 0;

        foreach ($companies as $company) {
            if ($tenancy->exists($company)) {
                $this->info("[skip] {$company->name} -> {$tenancy->databaseName($company)} already exists");
                $skipped++;

                continue;
            }

            $this->info("[ok] {$company->name} -> {$tenancy->databaseName($company)}");
            $tenancy->provision($company);
            $provisioned++;
        }

        $this->info("Tenant databases provisioned: {$provisioned} (skipped {$skipped}).");

        return self::SUCCESS;
    }
}