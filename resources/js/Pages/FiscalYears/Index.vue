<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';

interface FiscalYear {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    status: string;
    periods_count: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('fiscal_year.create') || page.props.auth.user.is_super_admin);
const canClose = computed(() => page.props.auth.permissions.includes('fiscal_year.close') || page.props.auth.user.is_super_admin);
const canReopen = computed(() => page.props.auth.permissions.includes('fiscal_year.reopen') || page.props.auth.user.is_super_admin);

defineProps<{
    fiscalYears: {
        data: FiscalYear[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string };
}>();

const closeYear = (fiscalYear: FiscalYear) => {
    if (confirm(`Close fiscal year ${fiscalYear.name}? All periods must be closed first. Net income will be closed to retained earnings and balances carried to the next year.`)) {
        router.post(route('fiscal-years.close', fiscalYear.id), {}, { preserveScroll: true });
    }
};

const reopenYear = (fiscalYear: FiscalYear) => {
    if (confirm(`Reopen fiscal year ${fiscalYear.name}? This lets you post adjustments into its closed periods.`)) {
        router.post(route('fiscal-years.reopen', fiscalYear.id), {}, { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Fiscal Years" description="Accounting years and their open/closed state.">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('fiscal-years.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Fiscal Year
                    </Link>
                </template>
            </PageHeader>

            <div v-if="fiscalYears.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="calendar" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No fiscal years found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Fiscal Year</th>
                            <th class="px-4 py-3 font-semibold">Start</th>
                            <th class="px-4 py-3 font-semibold">End</th>
                            <th class="px-4 py-3 font-semibold">Periods</th>
                            <th class="px-4 py-3 font-semibold">State</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="fiscalYear in fiscalYears.data" :key="fiscalYear.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                                    {{ fiscalYear.name }}
                                    <span
                                        v-if="fiscalYear.is_active"
                                        class="rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-900/50 dark:text-green-300"
                                    >
                                        ACTIVE
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(fiscalYear.start_date) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(fiscalYear.end_date) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ fiscalYear.periods_count }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="fiscalYear.status" /></td>
                            <td class="px-4 py-3">
                                <span class="text-xs capitalize text-gray-600 dark:text-gray-300">{{ fiscalYear.is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="route('accounting-periods.index', { fiscal_year_id: fiscalYear.id })"
                                        class="text-xs font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white"
                                    >
                                        Periods
                                    </Link>
                                    <button
                                        v-if="canClose && fiscalYear.status === 'open' && !fiscalYear.is_active"
                                        type="button"
                                        class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                                        @click="closeYear(fiscalYear)"
                                    >
                                        Close
                                    </button>
                                    <button
                                        v-if="canReopen && fiscalYear.status === 'closed'"
                                        type="button"
                                        class="text-xs font-medium text-amber-600 hover:text-amber-800 dark:text-amber-400"
                                        @click="reopenYear(fiscalYear)"
                                    >
                                        Reopen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="fiscalYears.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>