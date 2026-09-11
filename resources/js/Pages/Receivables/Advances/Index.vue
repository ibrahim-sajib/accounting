<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface CustomerBrief {
    id: number;
    code?: string | null;
    name: string;
}

interface AdvanceRow {
    id: number;
    receipt_no: string | null;
    receipt_date: string;
    customer: CustomerBrief | null;
    amount: number;
    applied_amount: number;
    balance: number;
}

interface CustomerOption {
    value: number;
    label: string;
}

interface AccountOption {
    value: number;
    label: string;
}

const props = defineProps<{
    advances: {
        data: AdvanceRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    customers: CustomerOption[];
    accounts: AccountOption[];
    today?: string;
}>();

const showCreate = ref(false);

const form = useForm({
    customer_id: '' as number | '',
    receipt_date: props.today ?? new Date().toISOString().slice(0, 10),
    account_id: props.accounts[0]?.value ?? ('' as number | ''),
    amount: '',
    reference: '',
    memo: '',
});

const openCreate = () => {
    form.clearErrors();
    form.reset();
    form.receipt_date = props.today ?? new Date().toISOString().slice(0, 10);
    form.account_id = props.accounts[0]?.value ?? ('' as number | '');
    showCreate.value = true;
};

const submit = () => {
    form.post(route('advances.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Customer Advances"
                :description="`${advances.total ?? 0} advance receipt(s) — unissued payments to be applied to future invoices`"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="openCreate"
                    >
                        <AppIcon name="currency" class="h-4 w-4" />
                        Record Advance
                    </button>
                </template>
            </PageHeader>

            <div v-if="advances.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="currency" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No advances recorded yet.</p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Advance #</th>
                            <th class="px-4 py-3 font-semibold">Customer</th>
                            <th class="px-4 py-3 text-right font-semibold">Date</th>
                            <th class="px-4 py-3 text-right font-semibold">Amount</th>
                            <th class="px-4 py-3 text-right font-semibold">Applied</th>
                            <th class="px-4 py-3 text-right font-semibold">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in advances.data" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('advances.show', row.id)" class="font-mono font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ row.receipt_no ?? 'Advance' }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.customer?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ formatDate(row.receipt_date) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.amount) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">{{ formatMoney(row.applied_amount) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold" :class="row.balance > 0 ? 'text-gray-900 dark:text-white' : 'text-green-600 dark:text-green-400'">
                                {{ formatMoney(row.balance) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="advances.links" />
            </div>
        </div>

        <Modal :show="showCreate" max-width="md" @close="showCreate = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Record Customer Advance</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Journal: Cash/Bank Dr | Customer Advances (Liability) Cr. The advance can be applied to invoices later.
                </p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="adv_customer" value="Customer" />
                        <select
                            id="adv_customer"
                            v-model="form.customer_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            required
                        >
                            <option value="">Select customer…</option>
                            <option v-for="customer in customers" :key="customer.value" :value="customer.value">{{ customer.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.customer_id" />
                    </div>

                    <div>
                        <InputLabel for="adv_date" value="Advance date" />
                        <TextInput id="adv_date" v-model="form.receipt_date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.receipt_date" />
                    </div>

                    <div>
                        <InputLabel for="adv_account" value="Deposit to account" />
                        <select
                            id="adv_account"
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
                        <InputLabel for="adv_amount" value="Amount" />
                        <TextInput id="adv_amount" v-model="form.amount" type="number" step="0.0001" min="0" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.amount" />
                    </div>

                    <div>
                        <InputLabel for="adv_reference" value="Reference (optional)" />
                        <TextInput id="adv_reference" v-model="form.reference" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.reference" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="adv_memo" value="Memo (optional)" />
                        <TextInput id="adv_memo" v-model="form.memo" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.memo" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showCreate = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Post Advance</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>