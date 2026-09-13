<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Link, router } from '@inertiajs/vue3';
import { formatMoney } from '@/utils/formatMoney';

interface BudgetRow {
    id: number;
    name: string;
    status: string;
    notes: string | null;
    fiscal_year_name: string | null;
    fiscal_year: { id: number; name: string; start_date: string; end_date: string } | null;
    total_budgeted: number;
    line_count: number;
}

const props = defineProps<{
    budgets: {
        data: BudgetRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status?: string };
    statuses: { value: string; label: string }[];
}>();

const applyStatus = (status: string) => {
    router.get(route('budgets.index'), { status: status || undefined }, { preserveState: true, preserveScroll: true });
};

const destroyBudget = (b: BudgetRow) => {
    if (confirm(`Delete budget "${b.name}"?`)) {
        router.delete(route('budgets.destroy', b.id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader title="Budgets" description="Annual operating budgets compared against actual postings.">
            <template #actions>
                <Link :href="route('budgets.create')" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    New Budget
                </Link>
            </template>
        </PageHeader>

        <div class="mx-auto max-w-6xl">
            <div class="mb-4 flex items-center gap-3">
                <div class="w-56">
                    <InputLabel for="status_filter" value="Status" />
                    <select
                        id="status_filter"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters?.status ?? ''"
                        @change="(e) => applyStatus((e.target as HTMLSelectElement).value)"
                    >
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Budget</th>
                            <th class="px-4 py-3 font-semibold">Fiscal Year</th>
                            <th class="px-4 py-3 text-right font-semibold">Lines</th>
                            <th class="px-4 py-3 text-right font-semibold">Total Budgeted</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="b in budgets.data" :key="b.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ b.name }}</div>
                                <div v-if="b.notes" class="text-xs text-gray-400">{{ b.notes }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ b.fiscal_year_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-gray-300">{{ b.line_count }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(b.total_budgeted) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="b.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('budgets.show', b.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">View</Link>
                                <Link v-if="b.status === 'draft'" :href="route('budgets.edit', b.id)" class="ml-3 text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Edit</Link>
                                <button v-if="b.status === 'draft'" type="button" class="ml-3 text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="destroyBudget(b)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="budgets.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                No budgets yet. Create one to start tracking your annual plan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="budgets.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>