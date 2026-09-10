<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { computed } from 'vue';

interface Props {
    summary: {
        company_name: string | null;
        fiscal_year: string | null;
        active_period: string | null;
        accounting_basis: string | null;
    };
}

const props = defineProps<Props>();
const page = usePage();

const currentCompany = computed(() => page.props.current_company);
const companies = computed(() => page.props.companies ?? []);

const quickLinks = computed(() => [
    { label: 'Companies', href: route('companies.index'), icon: 'building', desc: 'Manage organizations' },
    { label: 'Branches', href: route('branches.index'), icon: 'branch', desc: 'Branch structure' },
    { label: 'Users', href: route('users.index'), icon: 'users', desc: 'Team & access' },
    { label: 'Roles & Permissions', href: route('roles.index'), icon: 'shield', desc: 'Authorization' },
    { label: 'Fiscal Years', href: route('fiscal-years.index'), icon: 'calendar', desc: 'Accounting years' },
    { label: 'Accounting Periods', href: route('accounting-periods.index'), icon: 'period', desc: 'Monthly periods' },
    { label: 'Currencies', href: route('currencies.index'), icon: 'currency', desc: 'Multi-currency' },
    { label: 'Settings', href: route('settings.index'), icon: 'settings', desc: 'System configuration' },
]);
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Hero -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-700 p-6 text-white shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 class="text-xl font-semibold">
                            Welcome back, {{ page.props.auth.user.name }}
                        </h1>
                        <p class="mt-1 text-indigo-100">
                            {{ currentCompany?.name ?? 'No company selected' }}
                            <span v-if="currentCompany?.currency_code" class="ms-2 rounded bg-white/10 px-2 py-0.5 text-xs">
                                {{ currentCompany.currency_code }}
                            </span>
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-lg bg-white/10 px-3 py-2">
                            <div class="text-xs text-indigo-200">Basis</div>
                            <div class="font-medium capitalize">{{ props.summary.accounting_basis ?? '—' }}</div>
                        </div>
                        <div class="rounded-lg bg-white/10 px-3 py-2">
                            <div class="text-xs text-indigo-200">Fiscal Year</div>
                            <div class="font-medium">{{ props.summary.fiscal_year ?? '—' }}</div>
                        </div>
                        <div class="rounded-lg bg-white/10 px-3 py-2 sm:col-span-1">
                            <div class="text-xs text-indigo-200">Active Period</div>
                            <div class="font-medium">{{ props.summary.active_period ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company indicator -->
            <div v-if="!currentCompany" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                No active company. Select a company from the switcher in the top bar, or ask an administrator to create one.
            </div>

            <!-- Quick links -->
            <div>
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Setup Modules</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="link in quickLinks"
                        :key="link.label"
                        :href="link.href"
                        class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-800"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
                                <AppIcon :name="link.icon" class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ link.label }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ link.desc }}</div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Multi-company -->
            <div v-if="companies.length > 1" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Your Companies</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="company in companies"
                        :key="company.id"
                        class="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3 dark:border-gray-800"
                    >
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ company.name }}</div>
                            <div class="text-xs text-gray-500">{{ company.currency_code ?? '—' }} · {{ company.status }}</div>
                        </div>
                        <span
                            v-if="company.id === currentCompany?.id"
                            class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300"
                        >
                            Active
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>