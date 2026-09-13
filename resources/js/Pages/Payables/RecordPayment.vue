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

interface SupplierOption {
    value: number;
    label: string;
    outstanding: number;
}

interface AccountOption {
    value: number;
    label: string;
}

interface OutstandingBill {
    id: number;
    bill_no: string | null;
    bill_date: string | null;
    due_date: string | null;
    total: number;
    balance_due: number;
    days_overdue: number;
}

const props = defineProps<{
    suppliers: SupplierOption[];
    accounts: AccountOption[];
    selectedSupplierId: number | null;
    outstandingBills: OutstandingBill[];
    today?: string;
}>();

const amounts = ref<Record<number, string>>({});

const allocateAll = () => {
    const next: Record<number, string> = {};
    props.outstandingBills.forEach((bill) => {
        next[bill.id] = String(bill.balance_due);
    });
    amounts.value = next;
};

const clearAll = () => {
    amounts.value = {};
};

const onSupplierChange = (e: Event) => {
    const value = (e.target as HTMLSelectElement).value;
    amounts.value = {};
    router.get(route('supplier-payment.index'), {
        supplier_id: value || undefined,
    });
};

const allocations = computed(() =>
    props.outstandingBills
        .filter((bill) => Number(amounts.value[bill.id] ?? 0) > 0)
        .map((bill) => ({
            purchase_bill_id: bill.id,
            amount: Number(amounts.value[bill.id] ?? 0),
        })),
);

const total = computed(() => allocations.value.reduce((sum, allocation) => sum + allocation.amount, 0));

const form = useForm({
    supplier_id: props.selectedSupplierId ?? ('' as number | ''),
    payment_date: props.today ?? new Date().toISOString().slice(0, 10),
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
        .post(route('supplier-payment.store'), {
            preserveScroll: true,
            onSuccess: () => {
                amounts.value = {};
            },
        });
};

const selectedSupplier = computed(() =>
    props.suppliers.find((s) => s.value === Number(form.supplier_id)),
);
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                title="Record Supplier Payment"
                description="Allocate a payment across one or more posted bills"
            >
                <template #actions>
                    <Link
                        :href="route('payable-outstanding.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Outstanding
                    </Link>
                    <Link
                        :href="route('supplier-advances.index')"
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
                                <InputLabel for="pay_supplier" value="Supplier" />
                                <select
                                    id="pay_supplier"
                                    v-model="form.supplier_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                    required
                                    @change="onSupplierChange"
                                >
                                    <option value="">Select supplier…</option>
                                    <option v-for="supplier in suppliers" :key="supplier.value" :value="supplier.value">
                                        {{ supplier.label }} (due {{ formatMoney(supplier.outstanding) }})
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.supplier_id" />
                            </div>

                            <div>
                                <InputLabel for="pay_date" value="Payment date" />
                                <TextInput id="pay_date" v-model="form.payment_date" type="date" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.payment_date" />
                            </div>

                            <div>
                                <InputLabel for="pay_account" value="Pay from account" />
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
                                <div v-if="selectedSupplier" class="mt-1 flex items-center justify-between text-xs">
                                    <span class="text-gray-400">Supplier total due</span>
                                    <span class="font-mono text-gray-600 dark:text-gray-300">{{ formatMoney(selectedSupplier.outstanding) }}</span>
                                </div>
                                <InputError class="mt-2" :message="allocations.length === 0 ? 'Allocate at least one bill amount.' : ''" />
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <PrimaryButton :disabled="form.processing || allocations.length === 0">
                                Post Payment
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Outstanding bills
                                <span v-if="selectedSupplier" class="text-gray-400">for {{ selectedSupplier.label }}</span>
                            </h2>
                            <div class="flex gap-2">
                                <SecondaryButton type="button" class="!px-3 !py-1.5 !text-xs" @click="allocateAll">Allocate all</SecondaryButton>
                                <SecondaryButton type="button" class="!px-3 !py-1.5 !text-xs" @click="clearAll">Clear</SecondaryButton>
                            </div>
                        </div>

                        <p v-if="!selectedSupplier" class="px-5 py-12 text-center text-sm text-gray-400">
                            Select a supplier to see their outstanding bills.
                        </p>

                        <p v-else-if="outstandingBills.length === 0" class="px-5 py-12 text-center text-sm text-gray-400">
                            {{ selectedSupplier.label }} has no outstanding bills.
                        </p>

                        <table v-else class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Bill</th>
                                    <th class="px-4 py-3 text-right font-semibold">Due date</th>
                                    <th class="px-4 py-3 text-right font-semibold">Balance due</th>
                                    <th class="px-4 py-3 text-right font-semibold">Payment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr
                                    v-for="bill in outstandingBills"
                                    :key="bill.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    :class="bill.days_overdue > 0 ? 'bg-red-50/30 dark:bg-red-950/15' : ''"
                                >
                                    <td class="px-4 py-3">
                                        <Link :href="route('purchase.bills.show', bill.id)" class="font-mono font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                            {{ bill.bill_no ?? 'Draft' }}
                                        </Link>
                                        <div class="text-xs text-gray-400">
                                            {{ bill.bill_date ? formatDate(bill.bill_date) : '—' }}
                                            <template v-if="bill.days_overdue > 0">· {{ bill.days_overdue }}d overdue</template>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">
                                        {{ bill.due_date ? formatDate(bill.due_date) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(bill.balance_due) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            :id="`alloc_${bill.id}`"
                                            v-model="amounts[bill.id]"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            :max="String(bill.balance_due)"
                                            :placeholder="String(bill.balance_due)"
                                            class="ml-auto block w-36 rounded-md border-gray-300 text-right font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-xs text-gray-400">
                        Posting creates a balanced journal: Accounts Payable Dr (total) | Cash/Bank Cr (total). Each allocation is
                        checked against the bill's live balance server-side.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>