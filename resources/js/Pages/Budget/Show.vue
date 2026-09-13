<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface VarianceRow {
    account_id: number;
    code: string;
    name: string;
    type: string;
    budgeted: number;
    actual: number;
    variance: number;
    variance_pct: number | null;
}

interface DetailRow {
    id: number;
    account_code: string;
    account_name: string;
    period_id: number;
    period_name: string;
    budgeted_amount: number;
    actual_amount: number;
}

const props = defineProps<{
    budget: {
        id: number;
        name: string;
        status: string;
        notes: string | null;
        fiscal_year_id: number;
        fiscalYear: { id: number; name: string; start_date: string; end_date: string } | null;
        total_budgeted: number;
    };
    variance: {
        periods: { id: number; name: string }[];
        rows: VarianceRow[];
        detail: DetailRow[];
    };
}>();

const page = usePage();
const canPost = computed(() => page.props.auth.permissions.includes('budget.post') || page.props.auth.user.is_super_admin);
const canEdit = computed(() => page.props.auth.permissions.includes('budget.update') || page.props.auth.user.is_super_admin);

const postBudget = () => {
    if (confirm('Post this budget? Lines will be frozen for the fiscal year.')) {
        router.post(route('budgets.post', props.budget.id));
    }
};

const destroyBudget = () => {
    if (confirm('Delete this budget?')) {
        router.delete(route('budgets.destroy', props.budget.id));
    }
};

const totalActual = computed(() => props.variance.rows.reduce((s, r) => s + r.actual, 0));
const totalVariance = computed(() => props.variance.rows.reduce((s, r) => s + r.variance, 0));
const variancePct = computed(() =>
    props.budget.total_budgeted > 0 ? ((props.budget.total_budgeted - totalActual.value) / props.budget.total_budgeted) * 100 : null
);
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            :title="budget.name"
            :description="budget.fiscalYear ? `${budget.fiscalYear.name} · ${formatMoney(budget.total_budgeted)} budgeted` : ''"
        >
            <template #actions>
                <Link :href="route('budgets.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    All Budgets
                </Link>
                <Link
                    v-if="canEdit && budget.status === 'draft'"
                    :href="route('budgets.edit', budget.id)"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                >
                    Edit
                </Link>
                <button
                    v-if="canPost && budget.status === 'draft'"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    @click="postBudget"
                >
                    Post Budget
                </button>
                <button
                    v-if="canEdit && budget.status === 'draft'"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700"
                    @click="destroyBudget"
                >
                    Delete
                </button>
            </template>
        </PageHeader>

        <div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Status</div>
                    <div class="mt-1"><StatusBadge :status="budget.status" /></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Budgeted</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ formatMoney(budget.total_budgeted) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Actual (YTD)</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ formatMoney(totalActual) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Variance</div>
                    <div class="mt-1 font-mono text-lg font-semibold" :class="totalVariance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        {{ formatMoney(totalVariance) }}
                        <span v-if="variancePct !== null" class="text-sm text-gray-400">({{ variancePct.toFixed(1) }}%)</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Budget vs Actual by Account</h2>
                </div>
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Account</th>
                            <th class="px-4 py-3 text-right font-semibold">Budgeted</th>
                            <th class="px-4 py-3 text-right font-semibold">Actual</th>
                            <th class="px-4 py-3 text-right font-semibold">Variance</th>
                            <th class="px-4 py-3 text-right font-semibold">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in variance.rows" :key="row.account_id">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-gray-400">{{ row.code }}</span>
                                <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ row.name }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.budgeted) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.actual) }}</td>
                            <td class="px-4 py-3 text-right font-mono" :class="row.variance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                {{ formatMoney(row.variance) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-gray-300">
                                {{ row.variance_pct === null ? '—' : `${row.variance_pct.toFixed(1)}%` }}
                            </td>
                        </tr>
                        <tr v-if="variance.rows.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">No budget lines yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Period Detail</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-semibold">Account</th>
                                <th class="px-4 py-3 font-semibold">Period</th>
                                <th class="px-4 py-3 text-right font-semibold">Budgeted</th>
                                <th class="px-4 py-3 text-right font-semibold">Actual</th>
                                <th class="px-4 py-3 text-right font-semibold">Variance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="row in variance.detail" :key="row.id">
                                <td class="px-4 py-2.5">
                                    <span class="font-mono text-xs text-gray-400">{{ row.account_code }}</span>
                                    <span class="ml-2 text-gray-900 dark:text-white">{{ row.account_name }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ row.period_name }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.budgeted_amount) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.actual_amount) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono" :class="row.budgeted_amount - row.actual_amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ formatMoney(row.budgeted_amount - row.actual_amount) }}
                                </td>
                            </tr>
                            <tr v-if="variance.detail.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">No budget lines yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>