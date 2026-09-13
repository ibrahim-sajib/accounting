<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';
import { formatDate } from '@/utils/formatDate';

interface FiscalYearRow {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: string;
    opening_status: 'empty' | 'draft' | 'posted';
    opening_journal_id: number | null;
}

defineProps<{
    fiscalYears: FiscalYearRow[];
}>();

const badgeFor = (status: FiscalYearRow['opening_status']) => {
    switch (status) {
        case 'posted':
            return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300';
        case 'draft':
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300';
        default:
            return 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400';
    }
};

const labelFor = (status: FiscalYearRow['opening_status']) =>
    status === 'posted' ? 'Posted' : status === 'draft' ? 'Draft' : 'Not entered';
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl">
            <PageHeader title="Opening Balances" description="Enter the opening debit/credit balances for each fiscal year. Posting creates a single opening journal dated the first day of the year." />

            <div v-if="fiscalYears.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="opening" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Create a fiscal year first to enter opening balances.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-6 py-3 font-semibold">Fiscal Year</th>
                            <th class="px-6 py-3 font-semibold">Period</th>
                            <th class="px-6 py-3 font-semibold">Opening Status</th>
                            <th class="px-6 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="fy in fiscalYears" :key="fy.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-6 py-3 font-medium text-gray-900 dark:text-white">{{ fy.name }}</td>
                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400">
                                {{ formatDate(fy.start_date) }} – {{ formatDate(fy.end_date) }}
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="badgeFor(fy.opening_status)">
                                    {{ labelFor(fy.opening_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <div class="flex justify-end gap-4">
                                    <Link
                                        :href="route('opening-balances.entry', fy.id)"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{ fy.opening_status === 'posted' ? 'View Entry' : fy.opening_status === 'draft' ? 'Continue Entry' : 'Enter Balances' }}
                                    </Link>
                                    <Link
                                        v-if="fy.opening_journal_id"
                                        :href="route('journals.show', fy.opening_journal_id)"
                                        class="text-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        View Journal
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>