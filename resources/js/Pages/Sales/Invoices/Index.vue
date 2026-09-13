<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface CustomerBrief {
    id: number;
    code: string;
    name: string;
}

interface Invoice {
    id: number;
    invoice_no: string | null;
    invoice_date: string;
    due_date: string | null;
    total: string;
    amount_paid: string;
    status: string;
    paid_state: string | null;
    is_overdue: boolean;
    customer: CustomerBrief | null;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('sales.create') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    invoices: {
        data: Invoice[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; status?: string };
}>();

const paymentState = (invoice: Invoice) => {
    if (invoice.status !== 'posted') return 'draft';
    if (Number(invoice.is_overdue)) return 'overdue';
    return invoice.paid_state ?? 'unpaid';
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Sales Invoices" :description="`${invoices.total ?? 0} invoice(s)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('sales.invoices.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Invoice
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput :model-value="filters?.search" placeholder="Search by invoice no or customer..." />

                <select
                    :value="filters?.status ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-48"
                    @change="(e: Event) => router.get(route('sales.invoices.index'), { status: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>

            <div v-if="invoices.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="invoice" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No invoices found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Invoice</th>
                            <th class="px-4 py-3 font-semibold">Customer</th>
                            <th class="px-4 py-3 font-semibold">Issued</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                            <th class="px-4 py-3 text-right font-semibold">Paid</th>
                            <th class="px-4 py-3 text-right font-semibold">Balance</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('sales.invoices.show', invoice.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ invoice.invoice_no ?? 'Draft' }}
                                </Link>
                                <div class="text-xs text-gray-400">#{{ invoice.id }}</div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ invoice.customer?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                <div>{{ formatDate(invoice.invoice_date) }}</div>
                                <div v-if="invoice.due_date" class="text-xs text-gray-400">due {{ formatDate(invoice.due_date) }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(invoice.total) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-gray-300">{{ formatMoney(invoice.amount_paid) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(Number(invoice.total) - Number(invoice.amount_paid)) }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="paymentState(invoice)" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('sales.invoices.show', invoice.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="invoices.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>