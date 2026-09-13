<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CashBankTabs from '@/Components/CashBankTabs.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface CashAccount {
    id: number;
    name: string;
}

interface CounterAccount {
    value: number;
    label: string;
}

interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
}

interface Transaction {
    id: number;
    transaction_no: string;
    transaction_type: string;
    transaction_date: string;
    amount: number;
    reference: string | null;
    description: string | null;
    cash_account: { id: number; name: string } | null;
    bank_account: { id: number; account_name: string; bank_name: string } | null;
    journal_no: string | null;
    journal_id: number | null;
}

interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    transactions: { data: Transaction[]; links: PaginatorLink[] };
    cashAccounts: CashAccount[];
    bankAccounts: BankAccount[];
    counterAccounts: CounterAccount[];
    filters: { search?: string; type?: string };
}>();

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('bank.create') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('bank.delete') || page.props.auth.user.is_super_admin);

const accountLabel = (account: BankAccount) => `${account.account_name} (${account.bank_name})`;

const TYPE_OPTIONS = [
    { value: 'cash_receipt', label: 'Cash Receipt' },
    { value: 'cash_payment', label: 'Cash Payment' },
    { value: 'bank_deposit', label: 'Bank Deposit' },
    { value: 'bank_withdrawal', label: 'Bank Withdrawal' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'bank_charge', label: 'Bank Charge' },
    { value: 'bank_interest', label: 'Bank Interest' },
];

const TYPE_LABELS = Object.fromEntries(TYPE_OPTIONS.map((option) => [option.value, option.label]));

const showCreate = ref(false);

const form = useForm({
    transaction_type: 'cash_receipt',
    transaction_date: new Date().toISOString().slice(0, 10),
    amount: '0',
    cash_account_id: '' as number | string,
    bank_account_id: '' as number | string,
    to_bank_account_id: '' as number | string,
    counter_account_id: '' as number | string,
    reference: '',
    description: '',
});

const type = computed(() => form.transaction_type);
const requiresCash = computed(() => ['cash_receipt', 'cash_payment'].includes(type.value));
const depositOrWithdrawal = computed(() => ['bank_deposit', 'bank_withdrawal'].includes(type.value));
const requiresBank = computed(() => !['cash_receipt', 'cash_payment'].includes(type.value));
const requiresTransfer = computed(() => type.value === 'bank_transfer');
const requiresCounter = computed(() => ['cash_receipt', 'cash_payment', 'bank_charge', 'bank_interest'].includes(type.value));

const openCreate = () => {
    form.clearErrors();
    form.reset();
    form.transaction_date = new Date().toISOString().slice(0, 10);
    showCreate.value = true;
};

const submitCreate = () => {
    form.post(route('cash-bank.transactions.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
};

watch(type, () => {
    form.errors.transaction_type = '';
});

const destroy = (transaction: Transaction) => {
    if (confirm(`Delete transaction ${transaction.transaction_no}? Its journal will also be removed.`)) {
        router.delete(route('cash-bank.transactions.destroy', transaction.id), { preserveScroll: true });
    }
};

const typeFilter = ref(props.filters.type ?? '');
const search = ref(props.filters.search ?? '');

const applyFilters = () => {
    router.get(route('cash-bank.transactions'), {
        type: typeFilter.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Cash & Bank Transactions" description="Direct cash receipts/payments, bank deposits, withdrawals, transfers, charges and interest.">
                <template #actions>
                    <button
                        v-if="canCreate"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="openCreate"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Transaction
                    </button>
                </template>
            </PageHeader>

            <CashBankTabs active="transactions" />

            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <select v-model="typeFilter" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" @change="applyFilters">
                    <option value="">All types</option>
                    <option v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search number, reference, description…"
                    class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    @keyup.enter="applyFilters"
                />
            </div>

            <div v-if="transactions.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="bank" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No transactions found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Account</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Journal</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="tx in transactions.data" :key="tx.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ TYPE_LABELS[tx.transaction_type] ?? tx.transaction_type }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ tx.transaction_no }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(tx.transaction_date) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ tx.cash_account?.name ?? (tx.bank_account ? `${tx.bank_account.account_name} (${tx.bank_account.bank_name})` : '') }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ formatMoney(tx.amount) }}</td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="tx.journal_id"
                                    :href="route('journals.show', tx.journal_id)"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                >
                                    {{ tx.journal_no }}
                                </Link>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="canDelete"
                                    type="button"
                                    class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                                    @click="destroy(tx)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination v-if="transactions.links.length > 3" :links="transactions.links" />

            <!-- New transaction modal -->
            <Modal :show="showCreate" @close="showCreate = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">New Cash / Bank Transaction</h2>
                    <form @submit.prevent="submitCreate" class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="transaction_type" value="Transaction Type" required />
                                <select id="transaction_type" v-model="form.transaction_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <option v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.transaction_type" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="transaction_date" value="Date" required />
                                <TextInput id="transaction_date" v-model="form.transaction_date" type="date" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.transaction_date" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="amount" value="Amount" required />
                            <TextInput id="amount" v-model="form.amount" type="number" step="0.0001" min="0.0001" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.amount" class="mt-1" />
                        </div>

                        <div v-if="requiresCash" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="cash_account_id" value="Cash Account" required />
                                <select id="cash_account_id" v-model="form.cash_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select cash account…</option>
                                    <option v-for="account in (props.cashAccounts as CashAccount[])" :key="account.id" :value="account.id">{{ account.name }}</option>
                                </select>
                                <InputError :message="form.errors.cash_account_id" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="counter_account_id" value="Counter Account (Income / Expense)" required />
                                <select id="counter_account_id" v-model="form.counter_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select GL account…</option>
                                    <option v-for="account in (props.counterAccounts as CounterAccount[])" :key="account.value" :value="account.value">{{ account.label }}</option>
                                </select>
                                <InputError :message="form.errors.counter_account_id" class="mt-1" />
                            </div>
                        </div>

                        <div v-if="requiresBank && !depositOrWithdrawal" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="bank_account_id" value="Bank Account" required />
                                <select id="bank_account_id" v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select bank account…</option>
                                    <option v-for="account in (props.bankAccounts as BankAccount[])" :key="account.id" :value="account.id">{{ accountLabel(account) }}</option>
                                </select>
                                <InputError :message="form.errors.bank_account_id" class="mt-1" />
                            </div>
                            <div v-if="requiresTransfer">
                                <InputLabel for="to_bank_account_id" value="To Bank Account" required />
                                <select id="to_bank_account_id" v-model="form.to_bank_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select destination account…</option>
                                    <option v-for="account in (props.bankAccounts as BankAccount[]).filter((a) => a.id !== form.bank_account_id)" :key="account.id" :value="account.id">{{ accountLabel(account) }}</option>
                                </select>
                                <InputError :message="form.errors.to_bank_account_id" class="mt-1" />
                            </div>
                            <div v-if="requiresCounter">
                                <InputLabel for="counter_account_id" value="Counter Account (Income / Expense)" required />
                                <select id="counter_account_id" v-model="form.counter_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select GL account…</option>
                                    <option v-for="account in (props.counterAccounts as CounterAccount[])" :key="account.value" :value="account.value">{{ account.label }}</option>
                                </select>
                                <InputError :message="form.errors.counter_account_id" class="mt-1" />
                            </div>
                        </div>

                        <div v-if="depositOrWithdrawal" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="cash_account_id" value="Cash Account" required />
                                <select id="cash_account_id" v-model="form.cash_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select cash account…</option>
                                    <option v-for="account in (props.cashAccounts as CashAccount[])" :key="account.id" :value="account.id">{{ account.name }}</option>
                                </select>
                                <InputError :message="form.errors.cash_account_id" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="bank_account_id" value="Bank Account" required />
                                <select id="bank_account_id" v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select bank account…</option>
                                    <option v-for="account in (props.bankAccounts as BankAccount[])" :key="account.id" :value="account.id">{{ accountLabel(account) }}</option>
                                </select>
                                <InputError :message="form.errors.bank_account_id" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="reference" value="Reference" />
                                <TextInput id="reference" v-model="form.reference" type="text" class="mt-1 block w-full" maxlength="100" placeholder="e.g. cheque # " />
                                <InputError :message="form.errors.reference" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="description" value="Description" />
                                <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" maxlength="500" />
                                <InputError :message="form.errors.description" class="mt-1" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showCreate = false">
                                Cancel
                            </button>
                            <PrimaryButton :disabled="form.processing">Post Transaction</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>