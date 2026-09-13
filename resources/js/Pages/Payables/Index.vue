<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface SupplierBrief {
    id: number;
    code?: string | null;
    name: string;
}

interface TopSupplier {
    supplier_id: number;
    supplier: SupplierBrief;
    balance_due: number;
    bill_count: number;
}

interface RecentPayment {
    id: number;
    type: 'payment' | 'advance';
    payment_no: string | null;
    payment_date: string;
    supplier: SupplierBrief | null;
    amount: number;
    applied_amount: number;
}

const props = defineProps<{
    dashboard: {
        total_payable: number;
        overdue: number;
        paid_this_month: number;
        advance_balance: number;
        top_suppliers: TopSupplier[];
        recent_payments: RecentPayment[];
    };
}>();

const page = usePage();
const can = (permission: string) =>
    page.props.auth.user.is_super_admin || page.props.auth.permissions.includes(permission);

const payables = computed(() => props.dashboard);
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                title="Accounts Payable"
                description="Outstanding payables, payments and supplier advances"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('payable-outstanding.index')"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            Outstanding
                        </Link>
                        <Link
                            :href="route('payable-aging.index')"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            Aging Report
                        </Link>
                        <Link
                            v-if="can('payment.post')"
                            :href="route('supplier-payment.index')"
                            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700"
                        >
                            <AppIcon name="payment" class="h-4 w-4" />
                            Record Payment
                        </Link>
                        <Link
                            v-if="can('payables.advance')"
                            :href="route('supplier-advances.index')"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            <AppIcon name="currency" class="h-4 w-4" />
                            Supplier Advances
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Total payable</div>
                    <div class="mt-1 font-mono text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(payables.total_payable) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Overdue</div>
                    <div class="mt-1 font-mono text-xl font-bold" :class="payables.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                        {{ formatMoney(payables.overdue) }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Paid this month</div>
                    <div class="mt-1 font-mono text-xl font-bold text-green-600 dark:text-green-400">{{ formatMoney(payables.paid_this_month) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Unapplied advances</div>
                    <div class="mt-1 font-mono text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(payables.advance_balance) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Top suppliers by balance</h2>
                    </div>

                    <p v-if="payables.top_suppliers.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                        No outstanding balances.
                    </p>

                    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="row in payables.top_suppliers" :key="row.supplier_id" class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <Link :href="`${route('supplier-payment.index')}?supplier_id=${row.supplier_id}`" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ row.supplier?.name ?? 'Supplier' }}
                                </Link>
                                <div class="text-xs text-gray-400">
                                    {{ row.bill_count }} outstanding bill(s)
                                </div>
                            </div>
                            <div class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(row.balance_due) }}</div>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Recent payments & advances</h2>
                    </div>

                    <p v-if="payables.recent_payments.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                        No payments recorded yet.
                    </p>

                    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="payment in payables.recent_payments" :key="payment.id" class="flex items-center justify-between px-5 py-3 text-sm">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ payment.payment_no ?? 'Payment' }}
                                    <span
                                        v-if="payment.type === 'advance'"
                                        class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                    >
                                        advance
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ payment.supplier?.name ?? '—' }} · {{ formatDate(payment.payment_date) }}
                                    <template v-if="payment.type === 'advance' && Number(payment.applied_amount) > 0">
                                        · {{ formatMoney(payment.applied_amount) }} applied
                                    </template>
                                </div>
                            </div>
                            <div class="font-mono text-gray-900 dark:text-white">{{ formatMoney(payment.amount) }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>