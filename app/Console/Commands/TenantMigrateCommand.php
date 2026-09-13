<?php

namespace App\Console\Commands;

use App\Domain\Company\Models\Company;
use App\Domain\Tenant\Services\TenantManager;
use Illuminate\Console\Command;

class TenantMigrateCommand extends Command
{
    protected $signature = 'tenant:migrate {company? : Company ID; omit to re-migrate every tenant database}';

    protected $description = 'Re-run pending migrations (and master-data seeders) against company tenant databases.';

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

        $migrated = 0;

        foreach ($companies as $company) {
            if (! $tenancy->exists($company)) {
                $this->warn("[skip] {$company->name} has no tenant database yet (run tenant:provision first).");

                continue;
            }

            $this->info("[ok] {$company->name} -> {$tenancy->databaseName($company)}");
            $tenancy->migrate($company, true);
            $migrated++;
        }

        $this->info("Tenant databases migrated: {$migrated}.");

        return self::SUCCESS;
    }
}