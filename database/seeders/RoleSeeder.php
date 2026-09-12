<?php

namespace Database\Seeders;

use App\Domain\Rbac\Models\Permission;
use App\Domain\Rbac\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    protected array $roles = [
        'super-admin' => [
            'name' => 'Super Admin',
            'all_permissions' => true,
        ],
        'company-admin' => [
            'name' => 'Company Admin',
            'permissions' => ['*'],
        ],
        'accountant' => [
            'name' => 'Accountant',
            'permissions' => [
                'account.*', 'journal.*', 'opening_balance.*', 'report.*', 'dashboard.view',
                'sales.view', 'sales.create', 'sales.update', 'sales.post', 'sales.delete',
                'purchase.view', 'purchase.create', 'purchase.update', 'purchase.post', 'purchase.delete',
                'receipt.*', 'receivables.*', 'payment.*', 'payables.*', 'bank.*', 'expense.*',
                'inventory.*', 'fixed_asset.*', 'payroll.view', 'payroll.post', 'budget.*',
                'fiscal_year.view', 'period.view', 'currency.view', 'tax.view',
                'accounting_config.view', 'accounting_config.update',
                'customer.view', 'supplier.view', 'product.view', 'warehouse.view',
                'audit.view', 'approval.view', 'approval.approve',
            ],
        ],
        'sales-executive' => [
            'name' => 'Sales Executive',
            'permissions' => [
                'dashboard.view', 'sales.*', 'receipt.create', 'receipt.view', 'receivables.view', 'receivables.advance',
                'customer.view', 'product.view', 'report.view',
            ],
        ],
        'purchase-executive' => [
            'name' => 'Purchase Executive',
            'permissions' => [
                'dashboard.view', 'purchase.*', 'payment.view', 'payment.create',
                'supplier.view', 'product.view', 'report.view', 'payables.view', 'payables.advance',
            ],
        ],
        'inventory-manager' => [
            'name' => 'Inventory Manager',
            'permissions' => [
                'dashboard.view', 'inventory.*', 'product.*', 'warehouse.*',
                'report.view',
            ],
        ],
        'hr-payroll-manager' => [
            'name' => 'HR / Payroll Manager',
            'permissions' => [
                'dashboard.view', 'payroll.*',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'permissions' => [
                'dashboard.view', 'report.view', 'company.view', 'branch.view',
                'account.view', 'sales.view', 'purchase.view', 'customer.view',
                'supplier.view', 'product.view', 'warehouse.view', 'inventory.view',
                'bank.view', 'expense.view', 'payroll.view', 'budget.view', 'fixed_asset.view',
                'fiscal_year.view', 'period.view', 'currency.view', 'tax.view',
                'journal.view', 'opening_balance.view', 'receipt.view', 'receivables.view', 'payment.view', 'payables.view', 'audit.view', 'accounting_config.view', 'approval.view',
            ],
        ],
    ];

    public function run(?int $companyId = null): void
    {
        $allPermissionIds = Permission::query()->pluck('id')->all();

        foreach ($this->roles as $slug => $config) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug, 'company_id' => $companyId],
                [
                    'name' => $config['name'],
                    'description' => $config['name'].' role',
                    'is_system' => true,
                ]
            );

            if (! empty($config['all_permissions'])) {
                $role->permissions()->sync($allPermissionIds);
                continue;
            }

            $permissionIds = collect($config['permissions'])
                ->flatMap(fn ($pattern) => $this->resolvePattern($pattern, $allPermissionIds))
                ->unique()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }

    private function resolvePattern(string $pattern, array $allPermissionIds): array
    {
        if ($pattern === '*') {
            return $allPermissionIds;
        }

        if (Str::endsWith($pattern, '*')) {
            $moduleLike = Str::beforeLast($pattern, '.');
            return Permission::query()
                ->where('module', $moduleLike)
                ->pluck('id')
                ->all();
        }

        return Permission::query()->where('slug', $pattern)->pluck('id')->all();
    }
}