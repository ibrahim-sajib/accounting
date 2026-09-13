<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface CustomerOption {
    value: number;
    label: string;
    outstanding: number;
}

interface AccountOption {
    value: number;
    label: string;
}

interface OutstandingInvoice {
    id: number;
    invoice_no: string | null;
    invoice_date: string | null;
    due_date: string | null;
    total: number;
    balance_due: number;
    days_overdue: number;
}

const props = defineProps<{
    customers: CustomerOption[];
    accounts: AccountOption[];
    selectedCustomerId: number | null;
    outstandingInvoices: OutstandingInvoice[];
    today?: string;
}>();

const amounts = ref<Record<number, string>>({});

const allocateAll = () => {
    const next: Record<number, string> = {};
    props.outstandingInvoices.forEach((invoice) => {
        next[invoice.id] = String(invoice.balance_due);
    });
    amounts.value = next;
};

const clearAll = () => {
    amounts.value = {};
};

const onCustomerChange = (e: Event) => {
    const value = (e.target as HTMLSelectElement).value;
    amounts.value = {};
    router.get(route('payment.index'), {
        customer_id: value || undefined,
    });
};

const allocations = computed(() =>
    props.outstandingInvoices
        .filter((invoice) => Number(amounts.value[invoice.id] ?? 0) > 0)
        .map((invoice) => ({
            sales_invoice_id: invoice.id,
            amount: Number(amounts.value[invoice.id] ?? 0),
        })),
);

const total = computed(() => allocations.value.reduce((sum, allocation) => sum + allocation.amount, 0));

const form = useForm({
    customer_id: props.selectedCustomerId ?? ('' as number | ''),
    receipt_date: props.today ?? new Date().toISOString().slice(0, 10),
    account_id: props.accounts[0]?.value ?? ('' as number | ''),
    reference: '',
    memo: '',
});

const submit = () => {
    form
        .transform(() => ({
            ...form.data(),
            allocations: allocations.value,
        }))
        .post(route('payment.store'), {
            preserveScroll: true,
            onSuccess: () => {
                amounts.value = {};
            },
        });
};

const selectedCustomer = computed(() =>
    props.customers.find((c) => c.value === Number(form.customer_id)),
);
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                title="Record Customer Payment"
                description="Allocate a payment across one or more posted invoices"
            >
                <template #actions>
                    <Link
                        :href="route('outstanding.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Outstanding
                    </Link>
                    <Link
                        :href="route('advances.index')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="currency" class="h-4 w-4" />
                        New Advance
                    </Link>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <form class="space-y-6 lg:col-span-1" @submit.prevent="submit">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Payment details</h2>

                        <div class="mt-4 space-y-4">
                            <div>
                                <InputLabel for="pay_customer" value="Customer" />
                                <select
                                    id="pay_customer"
                                    v-model="form.customer_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                    @change="onCustomerChange"
                                >
                                    <option value="">Select customer…</option>
                                    <option v-for="customer in customers" :key="customer.value" :value="customer.value">
                                        {{ customer.label }} (due {{ formatMoney(customer.outstanding) }})
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.customer_id" />
                            </div>

                            <div>
                                <InputLabel for="pay_date" value="Receipt date" />
                                <TextInput id="pay_date" v-model="form.receipt_date" type="date" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.receipt_date" />
                            </div>

                            <div>
                                <InputLabel for="pay_account" value="Deposit to account" />
                                <select
                                    id="pay_account"
                                    v-model="form.account_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                >
                                    <option value="">Select account…</option>
                                    <option v-for="account in accounts" :key="account.value" :value="account.value">{{ account.label }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.account_id" />
                            </div>

                            <div>
                                <InputLabel for="pay_reference" value="Reference (optional)" />
                                <TextInput id="pay_reference" v-model="form.reference" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="form.errors.reference" />
                            </div>

                            <div>
                                <InputLabel for="pay_memo" value="Memo (optional)" />
                                <TextInput id="pay_memo" v-model="form.memo" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="form.errors.memo" />
                            </div>

                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Total allocated</span>
                                    <span class="font-mono text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(total) }}</span>
                                </div>
                                <div v-if="selectedCustomer" class="mt-1 flex items-center justify-between text-xs">
                                    <span class="text-gray-400">Customer total due</span>
                                    <span class="font-mono text-gray-600 dark:text-gray-300">{{ formatMoney(selectedCustomer.outstanding) }}</span>
                                </div>
                                <InputError class="mt-2" :message="allocations.length === 0 ? 'Allocate at least one invoice amount.' : ''" />
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <PrimaryButton :disabled="form.processing || allocations.length === 0">
                                Post Receipt
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Outstanding invoices
                                <span v-if="selectedCustomer" class="text-gray-400">for {{ selectedCustomer.label }}</span>
                            </h2>
                            <div class="flex gap-2">
                                <SecondaryButton type="button" class="!px-3 !py-1.5 !text-xs" @click="allocateAll">Allocate all</SecondaryButton>
                                <SecondaryButton type="button" class="!px-3 !py-1.5 !text-xs" @click="clearAll">Clear</SecondaryButton>
                            </div>
                        </div>

                        <p v-if="!selectedCustomer" class="px-5 py-12 text-center text-sm text-gray-400">
                            Select a customer to see their outstanding invoices.
                        </p>

                        <p v-else-if="outstandingInvoices.length === 0" class="px-5 py-12 text-center text-sm text-gray-400">
                            {{ selectedCustomer.label }} has no outstanding invoices.
                        </p>

                        <table v-else class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Invoice</th>
                                    <th class="px-4 py-3 text-right font-semibold">Due date</th>
                                    <th class="px-4 py-3 text-right font-semibold">Balance due</th>
                                    <th class="px-4 py-3 text-right font-semibold">Payment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr
                                    v-for="invoice in outstandingInvoices"
                                    :key="invoice.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    :class="invoice.days_overdue > 0 ? 'bg-red-50/30 dark:bg-red-950/15' : ''"
                                >
                                    <td class="px-4 py-3">
                                        <Link :href="route('sales.invoices.show', invoice.id)" class="font-mono font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                            {{ invoice.invoice_no ?? 'Draft' }}
                                        </Link>
                                        <div class="text-xs text-gray-400">
                                            {{ invoice.invoice_date ? formatDate(invoice.invoice_date) : '—' }}
                                            <template v-if="invoice.days_overdue > 0">· {{ invoice.days_overdue }}d overdue</template>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">
                                        {{ invoice.due_date ? formatDate(invoice.due_date) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(invoice.balance_due) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            :id="`alloc_${invoice.id}`"
                                            v-model="amounts[invoice.id]"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            :max="String(invoice.balance_due)"
                                            :placeholder="String(invoice.balance_due)"
                                            class="ml-auto block w-36 rounded-md border-gray-300 text-right font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-xs text-gray-400">
                        Posting creates a balanced journal: Cash/Bank Dr (total) | Accounts Receivable Cr (total). Each allocation is
                        checked against the invoice's live balance server-side.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>