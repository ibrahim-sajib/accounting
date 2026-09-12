<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    active: string;
}>();

const TABS = [
    { key: 'overview', label: 'Overview', routeName: 'cash-bank.index' },
    { key: 'transactions', label: 'Transactions', routeName: 'cash-bank.transactions' },
    { key: 'accounts', label: 'Accounts', routeName: 'cash-bank.accounts' },
    { key: 'reconciliation', label: 'Reconciliation', routeName: 'cash-bank.reconciliations' },
];
</script>

<template>
    <div class="mb-4 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <Link
            v-for="tab in TABS"
            :key="tab.key"
            :href="route(tab.routeName)"
            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
            :class="tab.key === active ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'"
        >
            {{ tab.label }}
        </Link>
    </div>
</template>