<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';

interface Period {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    status: string;
    fiscal_year: { id: number; name: string };
}

const page = usePage();

const can = (slug: string) => page.props.auth.permissions.includes(slug) || page.props.auth.user.is_super_admin;

defineProps<{
    periods: Period[];
    fiscalYears: { id: number; name: string; status: string }[];
    activeFiscalYearId: number | null;
    statusOptions: { value: string; label: string }[];
}>();

const selectFiscalYear = (e: Event) => {
    router.get(route('accounting-periods.index'), {
        fiscal_year_id: (e.target as HTMLSelectElement).value || undefined,
    }, { preserveState: true, replace: true });
};

const doClose = (period: Period) => {
    if (confirm(`Close period ${period.name}? No further postings will be accepted.`)) {
        router.post(route('accounting-periods.close', period.id), {}, { preserveScroll: true });
    }
};

const doLock = (period: Period) => {
    if (confirm(`Lock period ${period.name}? It becomes read-only.`)) {
        router.post(route('accounting-periods.lock', period.id), {}, { preserveScroll: true });
    }
};

const doReopen = (period: Period) => {
    if (confirm(`Reopen period ${period.name}?`)) {
        router.post(route('accounting-periods.reopen', period.id), {}, { preserveScroll: true });
    }
};

const doSetActive = (period: Period) => {
    router.post(route('accounting-periods.set-active', period.id), {}, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Accounting Periods</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Monthly periods per fiscal year. Posting into closed/locked periods is rejected.
                    </p>
                </div>
                <div class="w-full sm:w-64">
                    <select
                        :value="String(activeFiscalYearId ?? '')"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        @change="selectFiscalYear"
                    >
                        <option v-for="fiscalYear in fiscalYears" :key="fiscalYear.id" :value="String(fiscalYear.id)">
                            {{ fiscalYear.name }} ({{ fiscalYear.status }})
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="periods.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="period" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No periods found for this fiscal year.</p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Period</th>
                            <th class="px-4 py-3 font-semibold">Start</th>
                            <th class="px-4 py-3 font-semibold">End</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="period in periods" :key="period.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                                    {{ period.name }}
                                    <span
                                        v-if="period.is_active"
                                        class="rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-900/50 dark:text-green-300"
                                    >
                                        ACTIVE
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(period.start_date) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(period.end_date) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="period.status" /></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2 text-xs font-medium">
                                    <button
                                        v-if="!period.is_active && period.status !== 'locked'"
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                        @click="doSetActive(period)"
                                    >
                                        Set Active
                                    </button>
                                    <button
                                        v-if="period.status === 'open' && can('period.close')"
                                        type="button"
                                        class="text-amber-600 hover:text-amber-800 dark:text-amber-400"
                                        @click="doClose(period)"
                                    >
                                        Close
                                    </button>
                                    <button
                                        v-if="period.status === 'open' && can('period.lock')"
                                        type="button"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400"
                                        @click="doLock(period)"
                                    >
                                        Lock
                                    </button>
                                    <button
                                        v-if="period.status !== 'open' && can('period.reopen')"
                                        type="button"
                                        class="text-green-600 hover:text-green-800 dark:text-green-400"
                                        @click="doReopen(period)"
                                    >
                                        Reopen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex gap-2 text-xs text-gray-500 dark:text-gray-400">
                <Link :href="route('fiscal-years.index')" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                    ← Back to Fiscal Years
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>