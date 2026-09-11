<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

interface Crumb {
    label: string;
    href?: string;
}

const MODULE_LABELS: Record<string, string> = {
    dashboard: 'Home',
    companies: 'Companies',
    branches: 'Branches',
    users: 'Users',
    roles: 'Roles & Permissions',
    accounts: 'Chart of Accounts',
    tax: 'Tax & VAT',
    'accounting-settings': 'Accounting Settings',
    'fiscal-years': 'Fiscal Years',
    'accounting-periods': 'Accounting Periods',
    currencies: 'Currencies',
    settings: 'System Settings',
    customers: 'Customers',
    suppliers: 'Suppliers',
    products: 'Products & Services',
    warehouses: 'Warehouses',
    sales: 'Sales',
    purchase: 'Purchase',
    journals: 'Journals',
    'opening-balances': 'Opening Balances',
    stock: 'Stock',
    'stock-adjustments': 'Inventory Adjustments',
    'stock-transfers': 'Inventory Transfers',
    receivables: 'Receivables',
    outstanding: 'Receivables',
    aging: 'Receivables',
    payment: 'Record Payment',
    advances: 'Customer Advances',
    profile: 'Profile',
};

const SINGULAR_LABELS: Record<string, string> = {
    companies: 'Company',
    branches: 'Branch',
    users: 'User',
    roles: 'Role',
    accounts: 'Account',
    'fiscal-years': 'Fiscal Year',
    customers: 'Customer',
    suppliers: 'Supplier',
    products: 'Product / Service',
    warehouses: 'Warehouse',
    sales: 'Sales Invoice',
    purchase: 'Purchase Bill',
    journals: 'Journal',
    'stock-adjustments': 'Stock Adjustment',
    'stock-transfers': 'Stock Transfer',
};

const CREATE_LABELS: Record<string, string> = {
    accounts: 'New Account',
    journals: 'New Journal',
    sales: 'New Sales Invoice',
    purchase: 'New Purchase Bill',
    'stock-adjustments': 'New Adjustment',
    'stock-transfers': 'New Transfer',
};

const crumbs = computed<Crumb[]>(() => {
    const routeName = route().current() ?? '';

    if (!routeName) {
        return [];
    }

    const base = routeName.split('.')[0];
    const moduleLabel = MODULE_LABELS[base];

    if (!moduleLabel) {
        return [];
    }

    if (base === 'dashboard') {
        return [{ label: 'Home' }];
    }

    const trail: Crumb[] = [{ label: 'Home', href: route('dashboard') }];

    if (base === 'profile') {
        return [...trail, { label: 'Profile' }];
    }

    const parts = routeName.split('.');
    const segment = parts[1];
    const subSegment = parts[2];

    const isCreateEdit =
        segment === 'create' || segment === 'edit' || subSegment === 'create' || subSegment === 'edit';

    if (isCreateEdit) {
        trail.push({
            label: moduleLabel,
            href: route().has(base + '.index') ? route(base + '.index') : undefined,
        });

        const verb = (subSegment === 'create' || subSegment === 'edit' ? subSegment : segment) as 'create' | 'edit';

        if (verb === 'create' && CREATE_LABELS[base]) {
            trail.push({ label: CREATE_LABELS[base] });
        } else {
            const subject = SINGULAR_LABELS[base] ?? moduleLabel;
            trail.push({ label: `${verb === 'create' ? 'Create' : 'Edit'} ${subject}` });
        }

        return trail;
    }

    trail.push({ label: moduleLabel });

    return trail;
});
</script>

<template>
    <nav
        v-if="crumbs.length > 1"
        class="mb-4 flex flex-wrap items-center gap-1.5 text-sm"
        aria-label="Breadcrumb"
    >
        <template v-for="(crumb, index) in crumbs" :key="index">
            <Link
                v-if="crumb.href"
                :href="crumb.href"
                class="text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
            >
                {{ crumb.label }}
            </Link>
            <span v-else aria-current="page" class="font-medium text-gray-800 dark:text-gray-200">
                {{ crumb.label }}
            </span>
            <svg
                v-if="index < crumbs.length - 1"
                class="h-4 w-4 text-gray-300 dark:text-gray-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </template>
    </nav>
</template>