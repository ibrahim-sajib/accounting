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
};

const CREATE_LABELS: Record<string, string> = {
    accounts: 'New Account',
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

    const segment = routeName.split('.')[1];

    if (segment === 'create' || segment === 'edit') {
        trail.push({
            label: moduleLabel,
            href: route().has(base + '.index') ? route(base + '.index') : undefined,
        });

        if (segment === 'create' && CREATE_LABELS[base]) {
            trail.push({ label: CREATE_LABELS[base] });
        } else {
            const verb = segment === 'create' ? 'Create' : 'Edit';
            const subject = SINGULAR_LABELS[base] ?? moduleLabel;

            trail.push({ label: `${verb} ${subject}` });
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