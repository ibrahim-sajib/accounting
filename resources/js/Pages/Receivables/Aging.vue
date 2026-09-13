<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';
import { formatMoney } from '@/utils/formatMoney';

interface CustomerBrief {
    id: number;
    code?: string | null;
    name: string;
}

interface OutstandingRow {
    id: number;
    invoice_no: string | null;
    invoice_date: string | null;
    due_date: string | null;
    customer_id: number;
    customer: CustomerBrief | null;
    total: number;
    amount_paid: number;
    write_off_amount: number;
    balance_due: number;
    days_overdue: number;
    paid_state: string;
}

interface AgingRow {
    customer_id: number;
    customer: CustomerBrief | null;
    current: number;
    b1_30: number;
    b31_60: number;
    b61_90: number;
    b90: number;
    total: number;
    invoices: OutstandingRow[];
}

const props = defineProps<{
    rows: AgingRow[];
}>();

const bucketLabels: Record<string, string> = {
    current: 'Current',
    b1_30: '1–30 days',
    b31_60: '31–60 days',
    b61_90: '61–90 days',
    b90: '90+ days',
};

const bucketKeys = ['current', 'b1_30', 'b31_60', 'b61_90', 'b90'] as const;

const totalFor = (bucket: string) => props.rows.reduce((sum, row) => sum + Number((row as any)[bucket] ?? 0), 0);
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Receivables Aging Report"
                description="Outstanding balances bucketed by days past due"
            >
                <template #actions>
                    <Link
                        :href="route('outstanding.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Outstanding
                    </Link>
                </template>
            </PageHeader>

            <div v-if="rows.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="alert" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No outstanding balances to age.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[1080px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Customer</th>
                            <th v-for="label in Object.values(bucketLabels)" :key="label" class="px-4 py-3 text-right font-semibold">{{ label }}</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in rows" :key="row.customer_id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ row.customer?.name ?? 'Customer' }}</div>
                                <div class="flex flex-wrap gap-2 pt-1.5">
                                    <span v-if="row.invoices.length" class="text-xs text-gray-400">{{ row.invoices.length }} invoice(s)</span>
                                </div>
                            </td>
                            <td v-for="key in bucketKeys" :key="key" class="px-4 py-3 text-right">
                                <span v-if="Number(row[key]) > 0" class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(row[key]) }}</span>
                                <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-gray-900 dark:text-white">{{ formatMoney(row.total) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-medium text-gray-700 dark:bg-gray-800/50 dark:text-gray-200">
                            <td class="px-4 py-3">Totals</td>
                            <td v-for="key in bucketKeys" :key="key" class="px-4 py-3 text-right font-mono">{{ formatMoney(totalFor(key)) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ formatMoney(rows.reduce((sum, row) => sum + Number(row.total), 0)) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div v-if="rows.some((row) => row.invoices.length > 0)" class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Drill-down — invoices by bucket</h3>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="row in rows" :key="row.customer_id" class="rounded-lg border border-gray-100 p-3 dark:border-gray-800">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ row.customer?.name ?? 'Customer' }}</div>
                            <ul class="mt-2 space-y-1.5">
                                <li v-for="invoice in row.invoices" :key="invoice.id" class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <Link :href="route('sales.invoices.show', invoice.id)" class="font-mono text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                            {{ invoice.invoice_no ?? 'Draft' }}
                                        </Link>
                                        <span class="text-gray-400">
                                            {{ bucketLabels[(invoice.days_overdue <= 0 ? 'current' : invoice.days_overdue <= 30 ? 'b1_30' : invoice.days_overdue <= 60 ? 'b31_60' : invoice.days_overdue <= 90 ? 'b61_90' : 'b90')] }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-gray-700 dark:text-gray-200">{{ formatMoney(invoice.balance_due) }}</span>
                                        <StatusBadge :status="invoice.paid_state" />
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>