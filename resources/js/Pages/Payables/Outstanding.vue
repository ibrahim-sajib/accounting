<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router } from '@inertiajs/vue3';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface SupplierBrief {
    id: number;
    code?: string | null;
    name: string;
}

interface OutstandingRow {
    id: number;
    bill_no: string | null;
    bill_date: string | null;
    due_date: string | null;
    supplier_id: number;
    supplier: SupplierBrief | null;
    total: number;
    amount_paid: number;
    balance_due: number;
    days_overdue: number;
    paid_state: string;
}

interface SupplierOption {
    value: number;
    label: string;
    outstanding: number;
}

const props = defineProps<{
    rows: OutstandingRow[];
    suppliers: SupplierOption[];
    filters: { search?: string; supplier_id?: number };
    totals: { balance_due: number; overdue: number; count: number };
}>();

const onSupplierChange = (e: Event) => {
    const value = (e.target as HTMLSelectElement).value;
    router.get(route('payable-outstanding.index'), {
        supplier_id: value || undefined,
        search: props.filters?.search || undefined,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Outstanding Payables"
                :description="`${totals.count} bill(s) with ${formatMoney(totals.balance_due)} owed`"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('supplier-payment.index')"
                            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700"
                        >
                            Record Payment
                        </Link>
                        <Link
                            :href="route('payable-aging.index')"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            Aging Report
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Balance due</div>
                    <div class="mt-1 font-mono text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(totals.balance_due) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Overdue</div>
                    <div class="mt-1 font-mono text-xl font-bold" :class="totals.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                        {{ formatMoney(totals.overdue) }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Open bills</div>
                    <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ totals.count }}</div>
                </div>
            </div>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="w-full sm:max-w-xs">
                    <SearchInput :model-value="filters?.search" placeholder="Search bill no. or supplier..." query-param="search" />
                </div>
                <select
                    :value="filters?.supplier_id ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-72"
                    @change="onSupplierChange"
                >
                    <option value="">All suppliers</option>
                    <option v-for="supplier in suppliers" :key="supplier.value" :value="supplier.value">{{ supplier.label }}</option>
                </select>
            </div>

            <div v-if="rows.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="bill" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No outstanding bills.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Bill</th>
                            <th class="px-4 py-3 font-semibold">Supplier</th>
                            <th class="px-4 py-3 text-right font-semibold">Due date</th>
                            <th class="px-4 py-3 text-right font-semibold">Amount</th>
                            <th class="px-4 py-3 text-right font-semibold">Paid</th>
                            <th class="px-4 py-3 text-right font-semibold">Balance due</th>
                            <th class="px-4 py-3 text-right font-semibold">Overdue</th>
                            <th class="px-4 py-3 text-right font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            :class="row.days_overdue > 0 ? 'bg-red-50/40 dark:bg-red-950/20' : ''"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('purchase.bills.show', row.id)" class="font-mono font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ row.bill_no ?? 'Draft' }}
                                </Link>
                                <div class="text-xs text-gray-400">{{ row.bill_date ? formatDate(row.bill_date) : '—' }}</div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.supplier?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">
                                {{ row.due_date ? formatDate(row.due_date) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.total) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">{{ formatMoney(row.amount_paid) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(row.balance_due) }}</td>
                            <td class="px-4 py-3 text-right">
                                <template v-if="row.days_overdue > 0">
                                    <span class="font-mono font-semibold text-red-600 dark:text-red-400">{{ row.days_overdue }} d</span>
                                </template>
                                <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <StatusBadge :status="row.paid_state" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>