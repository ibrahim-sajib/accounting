<?php

namespace Database\Seeders;

use App\Domain\Rbac\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * All module permissions across the system, grouped by module.
     * action = view | create | update | delete | approve | post | void | reopen
     */
    protected array $modules = [
        'company' => ['view', 'create', 'update', 'delete', 'switch'],
        'branch' => ['view', 'create', 'update', 'delete'],
        'user' => ['view', 'create', 'update', 'delete'],
        'role' => ['view', 'create', 'update', 'delete'],
        'settings' => ['view', 'update'],
        'fiscal_year' => ['view', 'create', 'update', 'delete', 'close', 'reopen'],
        'period' => ['view', 'create', 'update', 'delete', 'close', 'reopen', 'lock'],
        'currency' => ['view', 'create', 'update', 'delete'],
        'exchange_rate' => ['view', 'create', 'update', 'delete'],
        'tax' => ['view', 'create', 'update', 'delete'],
        'account' => ['view', 'create', 'update', 'delete'],
        'accounting_config' => ['view', 'update'],
        'customer' => ['view', 'create', 'update', 'delete'],
        'supplier' => ['view', 'create', 'update', 'delete'],
        'product' => ['view', 'create', 'update', 'delete'],
        'warehouse' => ['view', 'create', 'update', 'delete'],
        'inventory' => ['view', 'create', 'update', 'delete', 'adjust', 'transfer'],
        'journal' => ['view', 'create', 'update', 'delete', 'approve', 'post', 'void'],
        'opening_balance' => ['view', 'create', 'post'],
        'sales' => ['view', 'create', 'update', 'delete', 'approve', 'post', 'void', 'return'],
        'purchase' => ['view', 'create', 'update', 'delete', 'approve', 'post', 'void', 'return'],
        'receipt' => ['view', 'create', 'update', 'delete', 'approve', 'post', 'void'],
        'receivables' => ['view', 'advance', 'write_off'],
        'payables' => ['view', 'advance'],
        'payment' => ['view', 'create', 'update', 'delete', 'approve', 'post', 'void'],
        'bank' => ['view', 'create', 'update', 'delete', 'reconcile'],
        'expense' => ['view', 'create', 'update', 'delete', 'approve', 'post'],
        'fixed_asset' => ['view', 'create', 'update', 'delete', 'depreciate'],
        'payroll' => ['view', 'create', 'update', 'delete', 'process', 'post'],
        'budget' => ['view', 'create', 'update', 'delete', 'approve', 'post'],
        'report' => ['view', 'export'],
        'dashboard' => ['view'],
        'audit' => ['view'],
        'settings' => ['view', 'update'],
    ];

    public function run(): void
    {
        $now = now();

        $permissions = [];

        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action) {
                $slug = "{$module}.{$action}";
                $permissions[] = [
                    'name' => ucwords(str_replace('.', ' ', $slug)),
                    'slug' => $slug,
                    'module' => $module,
                    'action' => $action,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($permissions, 500) as $chunk) {
            Permission::query()->upsert(
                $chunk,
                ['slug'],
                ['name', 'module', 'action', 'updated_at']
            );
        }
    }
}