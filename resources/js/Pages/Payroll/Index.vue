<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PayrollTabs from '@/Components/PayrollTabs.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface RunRow {
    id: number;
    run_no: string | null;
    period: string | null;
    run_date: string;
    status: string;
    total_gross: number | string;
    total_deductions: number | string;
    total_net: number | string;
    employee_count: number;
    paid_state: string | null;
    paid_total: number | string;
    remaining: number | string;
    journal_no: string | null;
}

const props = defineProps<{
    runs: {
        data: RunRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    periods: { value: number; label: string }[];
    filters: { status?: string };
}>();

const page = usePage();
const canProcess = computed(() => page.props.auth.permissions.includes('payroll.process') || page.props.auth.user.is_super_admin);
const canCreateEmployee = computed(() => page.props.auth.permissions.includes('payroll.create') || page.props.auth.user.is_super_admin);

const processForm = useForm({ period_id: '' as number | '' });

const processPayroll = () => {
    processForm.post(route('payroll.process'), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Payroll Runs" :description="`${runs.total ?? 0} run(s)`">
                <template #actions>
                    <Link
                        v-if="canCreateEmployee"
                        :href="route('payroll.employees.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Employee
                    </Link>
                </template>
            </PageHeader>

            <PayrollTabs active="runs" />

            <form
                v-if="canProcess && periods.length > 0"
                class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center dark:border-gray-800 dark:bg-gray-900"
                @submit.prevent="processPayroll"
            >
                <AppIcon name="payroll" class="h-5 w-5 text-gray-400" />
                <div class="flex-1">
                    <select
                        id="pr_period"
                        v-model="processForm.period_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-60"
                        required
                    >
                        <option value="">Select period…</option>
                        <option v-for="p in periods" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Creates a draft run (one line per active employee) for the selected period.</p>
                </div>
                <PrimaryButton :disabled="processForm.processing || !processForm.period_id">
                    Process Payroll
                </PrimaryButton>
            </form>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <select
                    :value="filters?.status ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-44"
                    @change="(e: Event) => router.get(route('payroll.index'), { status: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                </select>
            </div>

            <div v-if="runs.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="payroll" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No payroll runs yet — select a period above to process one.</p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Run</th>
                            <th class="px-4 py-3 font-semibold">Period</th>
                            <th class="px-4 py-3 font-semibold">Run Date</th>
                            <th class="px-4 py-3 font-semibold">Employees</th>
                            <th class="px-4 py-3 text-right font-semibold">Gross</th>
                            <th class="px-4 py-3 text-right font-semibold">Net Payable</th>
                            <th class="px-4 py-3 font-semibold">Payments</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="run in runs.data" :key="run.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('payroll.runs.show', run.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ run.run_no ?? 'Draft' }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ run.period ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(run.run_date) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ run.employee_count }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(run.total_gross) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(run.total_net) }}</td>
                            <td class="px-4 py-3">
                                <span v-if="run.paid_state" class="flex items-center gap-2">
                                    <StatusBadge :status="run.paid_state" />
                                    <span v-if="run.paid_state === 'partial'" class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatMoney(run.remaining) }} left
                                    </span>
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="run.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('payroll.runs.show', run.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="runs.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>