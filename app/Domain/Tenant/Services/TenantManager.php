<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Database-per-tenant provisioning.
 *
 * Each company in the platform gets its own dedicated MySQL database
 * (`accounting_tenant_{id}`). Provisioning = create the database, run the
 * full migration set against it, then copy the company's registry rows
 * (company, branches, its users, RBAC) and re-run the master-data seeders.
 * Because the tenant database contains exactly ONE row in `companies`,
 * the seeders naturally scope themselves to that single company.
 *
 * The whole stack falls back to a no-op when the default driver isn't MySQL
 * (e.g. the SQLite in-memory test suite) — the shared-database, company_id
 * scoped model keeps working there.
 */
class TenantManager
{
    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled')
            && config('database.default') === 'mysql';
    }

    /**
     * Resolve the company's database name. Persisted at provision time on the
     * company record (company-name based); falls back to the legacy
     * {prefix}{id} convention for databases created before name-based naming.
     */
    public function databaseName(Company $company): string
    {
        return $company->database_name
            ?: $this->legacyDatabaseName($company);
    }

    public function legacyDatabaseName(Company $company): string
    {
        return config('tenancy.database_prefix').$company->id;
    }

    /**
     * Build a company-name-based database identifier, e.g. company "Demo
     * Business Ltd" -> `accounting_tenant_demo_business_ltd`. Respects the
     * MySQL 64-char identifier limit and stays unique by appending a numeric
     * suffix on collision.
     */
    public function buildDatabaseName(Company $company): string
    {
        $prefix = config('tenancy.database_prefix');
        $slug = Str::slug($company->name, '_');

        if ($slug === '') {
            $slug = 'company_'.$company->id;
        }

        $maxSlugLength = 64 - strlen($prefix);
        if (strlen($slug) > $maxSlugLength) {
            $slug = substr($slug, 0, $maxSlugLength);
        }

        $candidate = $prefix.$slug;
        $i = 2;

        while ($this->nameTaken($candidate)) {
            $suffix = (string) $i;
            $candidate = substr($prefix.$slug, 0, 64 - strlen($suffix)).$suffix;
            $i++;
        }

        return $candidate;
    }

    public function connectionName(Company $company): string
    {
        return 'tenant_'.$company->id;
    }

    /**
     * Connection the control-plane data (users, companies, RBAC, UCA, the
     * notification inbox) lives on. Falls back to the default when tenancy is
     * disabled (the SQLite test suite keeps using its own shared database).
     */
    public function platformConnection(): string
    {
        return $this->enabled() ? 'mysql' : config('database.default');
    }

    /**
     * Select the runtime default connection for the current request. Points at
     * the active company's tenant database, or back at the control-plane for
     * platform screens. Runs after auth/session bootstrap, before any
     * controller or Inertia share reads data.
     */
    public function configure(?int $companyId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $platform = $this->platformConnection();
        DB::setDefaultConnection($platform);

        if ($companyId === null) {
            return;
        }

        $company = Company::on($platform)->find($companyId);
        if ($company) {
            $this->registerConnection($company);
            DB::setDefaultConnection($this->connectionName($company));
        }
    }

    public function exists(Company $company): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $name = $this->databaseName($company);

        return (bool) DB::connection('mysql')->selectOne(
            'select SCHEMA_NAME from information_schema.SCHEMATA where SCHEMA_NAME = ?',
            [$name]
        );
    }

    protected function nameTaken(string $name): bool
    {
        return (bool) DB::connection('mysql')->selectOne(
            'select SCHEMA_NAME from information_schema.SCHEMATA where SCHEMA_NAME = ?',
            [$name]
        );
    }

    public function legacyExists(Company $company): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return (bool) DB::connection('mysql')->selectOne(
            'select SCHEMA_NAME from information_schema.SCHEMATA where SCHEMA_NAME = ?',
            [$this->legacyDatabaseName($company)]
        );
    }

    /**
     * Create, migrate and seed the company's dedicated database. Idempotent:
     * returns false when the database already exists or tenancy is disabled.
     *
     * Legacy databases created under the old {prefix}{id} naming are renamed
     * to the company-name-based convention here (MySQL has no RENAME DATABASE,
     * so the tables are moved across into a freshly created database).
     */
    public function provision(Company $company): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if (! $company->database_name && $this->legacyExists($company)) {
            $newName = $this->buildDatabaseName($company);
            $this->renameDatabase($this->legacyDatabaseName($company), $newName);
            $company->forceFill(['database_name' => $newName])->save();

            return true;
        }

        if ($this->exists($company)) {
            return false;
        }

        $name = $this->buildDatabaseName($company);
        $this->createDatabase($company, $name);
        // Persist the name <-> database mapping so every later lookup
        // (existence, migration, runtime connection selection) resolves it.
        $company->forceFill(['database_name' => $name])->save();
        $this->migrate($company, true);

        return true;
    }

    public function createDatabase(Company $company, ?string $name = null): void
    {
        $name = $name ?? $this->databaseName($company);

        try {
            $admin = DB::connection('tenant_admin');
            $admin->statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $admin->statement("GRANT ALL PRIVILEGES ON `{$name}`.* TO '".config('database.connections.mysql.username')."'@'%'");
            $admin->statement('FLUSH PRIVILEGES');
        } catch (Throwable $e) {
            Log::error('Failed to create tenant database.', [
                'company_id' => $company->id,
                'database' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            DB::purge('tenant_admin');
        }
    }

    /**
     * MySQL has no RENAME DATABASE statement: create the destination schema,
     * move every table across, re-grant the app user and drop the source.
     */
    protected function renameDatabase(string $from, string $to): void
    {
        try {
            $admin = DB::connection('tenant_admin');

            $meta = DB::connection('mysql')->selectOne(
                'select DEFAULT_CHARACTER_SET_NAME charset, DEFAULT_COLLATION_NAME collation from information_schema.SCHEMATA where SCHEMA_NAME = ?',
                [$from]
            );

            $charset = $meta->charset ?? 'utf8mb4';
            $collation = $meta->collation ?? 'utf8mb4_unicode_ci';

            $admin->statement("CREATE DATABASE `{$to}` CHARACTER SET {$charset} COLLATE {$collation}");

            $tables = DB::connection('mysql')->select(
                'select TABLE_NAME t from information_schema.TABLES where TABLE_SCHEMA = ?',
                [$from]
            );

            foreach ($tables as $table) {
                $admin->statement("RENAME TABLE `{$from}`.`{$table->t}` TO `{$to}`.`{$table->t}`");
            }

            $admin->statement("GRANT ALL PRIVILEGES ON `{$to}`.* TO '".config('database.connections.mysql.username')."'@'%'");
            $admin->statement('FLUSH PRIVILEGES');
            $admin->statement("DROP DATABASE `{$from}`");
        } catch (Throwable $e) {
            Log::error('Failed to rename tenant database.', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            DB::purge('tenant_admin');
        }
    }

    public function migrate(Company $company, bool $seed = false): void
    {
        $this->registerConnection($company);

        Artisan::call('migrate', [
            '--database' => $this->connectionName($company),
            '--force' => true,
        ]);

        if ($seed) {
            $this->seed($company);
        }
    }

    public function seed(Company $company): void
    {
        $conn = $this->connectionName($company);
        $platform = DB::getDefaultConnection();

        DB::setDefaultConnection($conn);

        try {
            $this->copyPlatformBoilerplate($company, $platform);
            $this->runMasterDataSeeders($company);
            DB::disconnect($conn);
        } catch (Throwable $e) {
            Log::error('Failed to seed tenant database.', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            DB::setDefaultConnection($platform);
        }
    }

    /**
     * Register the company's database as a runtime Laravel connection derived
     * from the platform `mysql` connection. Registering the config entry is
     * enough for Laravel's connection factory; it stays lazy until first use.
     */
    protected function registerConnection(Company $company): void
    {
        $conn = $this->connectionName($company);

        config([
            "database.connections.{$conn}" => array_merge(
                config('database.connections.mysql'),
                ['database' => $this->databaseName($company)]
            ),
        ]);

        DB::purge($conn);
    }

    /**
     * Copy the company's registry rows into the tenant database so every FK
     * (companies, users, branches, roles/permissions/pivots) resolves inside
     * the tenant database and the master-data seeders can run scoped to a
     * single company row.
     */
    protected function copyPlatformBoilerplate(Company $company, string $platform): void
    {
        $companyId = $company->id;

        // companies <-> users is a foreign-key cycle: companies.created_by ->
        // users and users.company_id -> companies. Insert the company row with
        // its created_by/updated_by stamps nulled first, copy the users, then
        // restore the stamps (the creating user is a super admin, so it is
        // always among the copied users). Every later copy (branches, roles,
        // pivots) then satisfies its created_by/manager_user_id FKs.
        $companyRow = (array) DB::connection($platform)->table('companies')->where('id', $companyId)->first();

        if ($companyRow !== []) {
            $createdBy = $companyRow['created_by'] ?? null;
            $updatedBy = $companyRow['updated_by'] ?? null;
            $companyRow['created_by'] = null;
            $companyRow['updated_by'] = null;
            DB::table('companies')->insertOrIgnore([$companyRow]);
        }

        // FK-safe order: users -> restore company stamps -> branches ->
        // permissions -> roles -> role_permissions -> user_roles ->
        // user_company_access. Super admins are always copied: every platform
        // seed and audit write stamps created_by with the acting super admin.
        $userIds = DB::connection($platform)->table('user_company_access')
            ->where('company_id', $companyId)
            ->pluck('user_id')
            ->merge(DB::connection($platform)->table('users')->where('company_id', $companyId)->pluck('id'))
            ->merge(DB::connection($platform)->table('users')->where('is_super_admin', true)->pluck('id'))
            ->unique()
            ->values()
            ->all();

        if ($userIds !== []) {
            $userIn = implode(',', array_fill(0, count($userIds), '?'));
            $userRows = DB::connection($platform)->table('users')
                ->whereRaw("id in ($userIn)", $userIds)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            foreach ($userRows as &$user) {
                // A user belongs to exactly one home company (or none). Rows
                // copied into this tenant whose home is another company (e.g.
                // a global super admin living on the demo company) get their
                // company_id nulled so the FK still resolves here; the tenant's
                // own users keep their id.
                $user['company_id'] = $user['company_id'] == $companyId ? $companyId : null;
            }

            foreach (array_chunk($userRows, 200) as $chunk) {
                DB::table('users')->insertOrIgnore($chunk);
            }

            if ($companyRow !== [] && ($createdBy !== null || $updatedBy !== null)) {
                $stamps = [];
                if ($createdBy !== null && in_array($createdBy, $userIds, true)) {
                    $stamps['created_by'] = $createdBy;
                }
                if ($updatedBy !== null && in_array($updatedBy, $userIds, true)) {
                    $stamps['updated_by'] = $updatedBy;
                }
                if ($stamps !== []) {
                    DB::table('companies')->where('id', $companyId)->update($stamps);
                }
            }
        }

        $this->copyTable($platform, 'branches', 'company_id = ?', [$companyId]);
        $this->copyTable($platform, 'permissions', '1 = 1', []);

        $roleIds = DB::connection($platform)->table('roles')
            ->where('company_id', $companyId)
            ->orWhereNull('company_id')
            ->pluck('id')
            ->all();

        if ($roleIds !== []) {
            $roleIn = implode(',', array_fill(0, count($roleIds), '?'));
            $this->copyTable($platform, 'roles', "id in ($roleIn)", $roleIds);
            $this->copyTable($platform, 'role_permissions', "role_id in ($roleIn)", $roleIds);
        }

        if ($userIds !== [] && $roleIds !== []) {
            $userIn = implode(',', array_fill(0, count($userIds), '?'));
            $roleIn = implode(',', array_fill(0, count($roleIds), '?'));
            $this->copyTable(
                $platform,
                'user_roles',
                "user_id in ($userIn) and role_id in ($roleIn)",
                array_merge($userIds, $roleIds)
            );
        }

        $this->copyTable($platform, 'user_company_access', 'company_id = ?', [$companyId]);
    }

    protected function copyTable(string $platform, string $table, string $where, array $bindings): void
    {
        $rows = DB::connection($platform)->table($table)
            ->whereRaw($where, $bindings)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            if ($chunk !== []) {
                DB::table($table)->insertOrIgnore($chunk);
            }
        }
    }

    /**
     * Keep a platform user's row + access + roles mirrored into every tenant
     * database the user can reach. Tenant DBs stay FK-consistent for audit
     * stamps (created_by) and notification target rows, and their RBAC copies
     * (roles/user_roles/user_company_access) stay in step with the platform
     * registry. Called after user create/update.
     */
    public function syncUser(User $user): void
    {
        if (! $this->enabled()) {
            return;
        }

        $platform = $this->platformConnection();

        $companyIds = DB::connection($platform)->table('user_company_access')
            ->where('user_id', $user->id)
            ->pluck('company_id')
            ->push($user->company_id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        foreach ($companyIds as $companyId) {
            if ($companyId === null) {
                continue;
            }

            $company = Company::on($platform)->withTrashed()->find($companyId);
            if (! $company) {
                continue;
            }

            $this->registerConnection($company);
            $tenant = DB::connection($this->connectionName($company));

            if (! $tenant->table('companies')->where('id', $companyId)->exists()) {
                continue;
            }

            $userRoleRows = DB::connection($platform)->table('user_roles')
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->get()
                ->map(fn ($row) => ['user_id' => $row->user_id, 'role_id' => $row->role_id, 'company_id' => $row->company_id, 'branch_id' => $row->branch_id])
                ->all();

            foreach ($userRoleRows as $row) {
                $role = (array) DB::connection($platform)->table('roles')->where('id', $row['role_id'])->first();
                if ($role === []) {
                    continue;
                }
                // Audit stamps may point at a user who is not (yet) in this
                // tenant — null them so the role copy cannot be FK-skipped.
                $role['created_by'] = null;
                $role['updated_by'] = null;
                $tenant->table('roles')->insertOrIgnore([$role]);

                $rolePermissions = DB::connection($platform)->table('role_permissions')
                    ->where('role_id', $row['role_id'])
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
                foreach ($rolePermissions as $rp) {
                    $tenant->table('role_permissions')->insertOrIgnore([$rp]);
                }
            }

            $userRow = (array) DB::connection($platform)->table('users')->where('id', $user->id)->first();
            $userRow['company_id'] = $user->company_id == $companyId ? $companyId : null;
            $tenant->table('users')->updateOrInsert(['id' => $user->id], $userRow);

            $tenant->table('user_roles')->where('user_id', $user->id)->where('company_id', $companyId)->delete();
            foreach ($userRoleRows as $row) {
                $tenant->table('user_roles')->insertOrIgnore([$row]);
            }

            $tenant->table('user_company_access')->where('user_id', $user->id)->where('company_id', $companyId)->delete();
            $accessRows = DB::connection($platform)->table('user_company_access')
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
            foreach ($accessRows as $row) {
                $tenant->table('user_company_access')->insertOrIgnore([$row]);
            }
        }
    }

    protected function runMasterDataSeeders(Company $company): void
    {
        (new \Database\Seeders\CurrencySeeder())->run();
        (new \Database\Seeders\FiscalYearSeeder())->run();
        (new \Database\Seeders\SystemSettingSeeder())->run();
        (new \Database\Seeders\ChartOfAccountsSeeder())->run();
        (new \Database\Seeders\TaxSeeder())->run();
        (new \Database\Seeders\AccountingSettingSeeder())->run();
        (new \Database\Seeders\MasterDataSeeder())->run();
        (new \Database\Seeders\ExpenseCategorySeeder())->run();
        (new \Database\Seeders\AssetCategorySeeder())->run();
        (new \Database\Seeders\PayrollSeeder())->run();
        (new \Database\Seeders\RoleSeeder())->run($company->id);
    }
}