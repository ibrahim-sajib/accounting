<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface CustomerBrief {
    id: number;
    code?: string | null;
    name: string;
}

interface TopCustomer {
    customer_id: number;
    customer: CustomerBrief;
    balance_due: number;
    invoice_count: number;
}

interface RecentReceipt {
    id: number;
    type: 'receipt' | 'advance';
    receipt_no: string | null;
    receipt_date: string;
    customer: CustomerBrief | null;
    amount: number;
    applied_amount: number;
}

const props = defineProps<{
    dashboard: {
        total_receivable: number;
        overdue: number;
        collected_this_month: number;
        advance_balance: number;
        top_customers: TopCustomer[];
        recent_receipts: RecentReceipt[];
    };
}>();

const page = usePage();
const can = (permission: string) =>
    page.props.auth.user.is_super_admin || page.props.auth.permissions.includes(permission);

const advanced = computed(() => props.dashboard);
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Accounts Receivable"
                description="Outstanding balances, collections and customer advances"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('outstanding.index')"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            Outstanding
                        </Link>
                        <Link
                            :href="route('aging.index')"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            Aging Report
                        </Link>
                        <Link
                            v-if="can('receipt.post')"
                            :href="route('payment.index')"
                            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700"
                        >
                            <AppIcon name="receipt" class="h-4 w-4" />
                            Record Payment
                        </Link>
                        <Link
                            v-if="can('receivables.advance')"
                            :href="route('advances.index')"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            <AppIcon name="currency" class="h-4 w-4" />
                            Customer Advances
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Total receivable</div>
                    <div class="mt-1 font-mono text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(advanced.total_receivable) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Overdue</div>
                    <div class="mt-1 font-mono text-xl font-bold" :class="advanced.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                        {{ formatMoney(advanced.overdue) }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Collected this month</div>
                    <div class="mt-1 font-mono text-xl font-bold text-green-600 dark:text-green-400">{{ formatMoney(advanced.collected_this_month) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Unapplied advances</div>
                    <div class="mt-1 font-mono text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(advanced.advance_balance) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Top customers by balance</h2>
                    </div>

                    <p v-if="advanced.top_customers.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                        No outstanding balances.
                    </p>

                    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="row in advanced.top_customers" :key="row.customer_id" class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <Link :href="`${route('payment.index')}?customer_id=${row.customer_id}`" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ row.customer?.name ?? 'Customer' }}
                                </Link>
                                <div class="text-xs text-gray-400">
                                    {{ row.invoice_count }} outstanding invoice(s)
                                </div>
                            </div>
                            <div class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(row.balance_due) }}</div>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Recent receipts & advances</h2>
                    </div>

                    <p v-if="advanced.recent_receipts.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                        No receipts recorded yet.
                    </p>

                    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="receipt in advanced.recent_receipts" :key="receipt.id" class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ receipt.receipt_no ?? 'Receipt' }}
                                    <span
                                        v-if="receipt.type === 'advance'"
                                        class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                    >
                                        advance
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ receipt.customer?.name ?? '—' }} · {{ formatDate(receipt.receipt_date) }}
                                    <template v-if="receipt.type === 'advance' && Number(receipt.applied_amount) > 0">
                                        · {{ formatMoney(receipt.applied_amount) }} applied
                                    </template>
                                </div>
                            </div>
                            <div class="font-mono text-gray-900 dark:text-white">{{ formatMoney(receipt.amount) }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>