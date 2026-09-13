<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CashBankTabs from '@/Components/CashBankTabs.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface CashAccount {
    id: number;
    name: string;
    gl_account_code: string;
    gl_account_id: number;
    balance: number;
    is_active: boolean;
}

interface BankAccount {
    id: number;
    account_name: string;
    account_no: string | null;
    bank_name: string;
    branch_name: string | null;
    gl_account_code: string;
    gl_account_id: number;
    currency_id: number | null;
    balance: number;
    last_reconciled_date: string | null;
    is_active: boolean;
}

interface AccountOption {
    value: number;
    label: string;
}

interface CurrencyOption {
    value: number;
    label: string;
}

const props = defineProps<{
    cashAccounts: CashAccount[];
    bankAccounts: BankAccount[];
    glAccounts: AccountOption[];
    currencies: CurrencyOption[];
}>();

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('bank.create') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('bank.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('bank.delete') || page.props.auth.user.is_super_admin);

// Cash account modal
const showCashModal = ref(false);
const editingCash = ref<CashAccount | null>(null);

const cashForm = useForm({
    name: '',
    gl_account_id: '' as number | string,
    is_active: true,
});

const openCashModal = (account?: CashAccount) => {
    cashForm.clearErrors();
    editingCash.value = account ?? null;
    cashForm.name = account?.name ?? '';
    cashForm.gl_account_id = account?.gl_account_id ?? '';
    cashForm.is_active = account?.is_active ?? true;
    showCashModal.value = true;
};

const submitCash = () => {
    if (editingCash.value) {
        cashForm.put(route('cash-bank.accounts.update-cash', editingCash.value.id), {
            onSuccess: () => {
                showCashModal.value = false;
                cashForm.reset();
            },
        });
    } else {
        cashForm.post(route('cash-bank.accounts.store-cash'), {
            onSuccess: () => {
                showCashModal.value = false;
                cashForm.reset();
            },
        });
    }
};

const destroyCash = (account: CashAccount) => {
    if (confirm(`Delete cash account "${account.name}"?`)) {
        router.delete(route('cash-bank.accounts.destroy-cash', account.id), { preserveScroll: true });
    }
};

// Bank account modal
const showBankModal = ref(false);
const editingBank = ref<BankAccount | null>(null);

const bankForm = useForm({
    account_name: '',
    account_no: '',
    bank_name: '',
    branch_name: '',
    currency_id: '' as number | string,
    gl_account_id: '' as number | string,
    is_active: true,
});

const openBankModal = (account?: BankAccount) => {
    bankForm.clearErrors();
    editingBank.value = account ?? null;
    bankForm.account_name = account?.account_name ?? '';
    bankForm.account_no = account?.account_no ?? '';
    bankForm.bank_name = account?.bank_name ?? '';
    bankForm.branch_name = account?.branch_name ?? '';
    bankForm.currency_id = account?.currency_id ?? '';
    bankForm.gl_account_id = account?.gl_account_id ?? '';
    bankForm.is_active = account?.is_active ?? true;
    showBankModal.value = true;
};

const submitBank = () => {
    if (editingBank.value) {
        bankForm.put(route('cash-bank.accounts.update-bank', editingBank.value.id), {
            onSuccess: () => {
                showBankModal.value = false;
                bankForm.reset();
            },
        });
    } else {
        bankForm.post(route('cash-bank.accounts.store-bank'), {
            onSuccess: () => {
                showBankModal.value = false;
                bankForm.reset();
            },
        });
    }
};

const destroyBank = (account: BankAccount) => {
    if (confirm(`Delete bank account "${account.account_name}"?`)) {
        router.delete(route('cash-bank.accounts.destroy-bank', account.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Cash & Bank Accounts" description="Register cash registers and bank accounts, each mapped to a GL account.">
                <template #actions>
                    <div v-if="canCreate" class="flex gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700" @click="openCashModal()">
                            <AppIcon name="plus" class="h-4 w-4" />
                            New Cash Account
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700" @click="openBankModal()">
                            <AppIcon name="plus" class="h-4 w-4" />
                            New Bank Account
                        </button>
                    </div>
                </template>
            </PageHeader>

            <CashBankTabs active="accounts" />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Cash accounts -->
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Cash Accounts</h3>
                    </div>
                    <div v-if="cashAccounts.length === 0" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        No cash accounts yet.
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="account in cashAccounts" :key="account.id" class="flex items-center justify-between px-5 py-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ account.name }}
                                    <span v-if="!account.is_active" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">INACTIVE</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ account.gl_account_code }}</div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(account.balance) }}</div>
                                <div v-if="canUpdate" class="flex gap-3">
                                    <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openCashModal(account)">Edit</button>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="destroyCash(account)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank accounts -->
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Bank Accounts</h3>
                    </div>
                    <div v-if="bankAccounts.length === 0" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        No bank accounts yet.
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="account in bankAccounts" :key="account.id" class="flex items-center justify-between px-5 py-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ account.account_name }}
                                    <span v-if="!account.is_active" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">INACTIVE</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ account.bank_name }}<template v-if="account.branch_name"> • {{ account.branch_name }}</template><template v-if="account.account_no"> • {{ account.account_no }}</template>
                                </div>
                                <div v-if="account.last_reconciled_date" class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                    Reconciled up to {{ account.last_reconciled_date }}
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(account.balance) }}</div>
                                <div v-if="canUpdate" class="flex gap-3">
                                    <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openBankModal(account)">Edit</button>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="destroyBank(account)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash account modal -->
            <Modal :show="showCashModal" @close="showCashModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ editingCash ? 'Edit Cash Account' : 'New Cash Account' }}</h2>
                    <form @submit.prevent="submitCash" class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="cash_name" value="Name" required />
                            <TextInput id="cash_name" v-model="cashForm.name" type="text" class="mt-1 block w-full" maxlength="100" placeholder="Main Cash Register" required />
                            <InputError :message="cashForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="cash_gl" value="GL Account" required />
                            <select id="cash_gl" v-model="cashForm.gl_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                <option value="">Select postable account…</option>
                                <option v-for="account in (props.glAccounts as AccountOption[])" :key="account.value" :value="account.value">{{ account.label }}</option>
                            </select>
                            <InputError :message="cashForm.errors.gl_account_id" class="mt-1" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" v-model="cashForm.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                            Active
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showCashModal = false">Cancel</button>
                            <PrimaryButton :disabled="cashForm.processing">{{ editingCash ? 'Save Changes' : 'Create Cash Account' }}</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Bank account modal -->
            <Modal :show="showBankModal" @close="showBankModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ editingBank ? 'Edit Bank Account' : 'New Bank Account' }}</h2>
                    <form @submit.prevent="submitBank" class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="account_name" value="Account Name" required />
                                <TextInput id="account_name" v-model="bankForm.account_name" type="text" class="mt-1 block w-full" maxlength="100" placeholder="DBBL Settlement A/C" required />
                                <InputError :message="bankForm.errors.account_name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="account_no" value="Account Number" required />
                                <TextInput id="account_no" v-model="bankForm.account_no" type="text" class="mt-1 block w-full" maxlength="60" required />
                                <InputError :message="bankForm.errors.account_no" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="bank_name" value="Bank Name" required />
                                <TextInput id="bank_name" v-model="bankForm.bank_name" type="text" class="mt-1 block w-full" maxlength="100" required />
                                <InputError :message="bankForm.errors.bank_name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="branch_name" value="Branch" />
                                <TextInput id="branch_name" v-model="bankForm.branch_name" type="text" class="mt-1 block w-full" maxlength="100" />
                                <InputError :message="bankForm.errors.branch_name" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="currency_id" value="Currency" />
                                <select id="currency_id" v-model="bankForm.currency_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <option value="">Default company currency</option>
                                    <option v-for="currency in (props.currencies as CurrencyOption[])" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                                </select>
                                <InputError :message="bankForm.errors.currency_id" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="bank_gl" value="GL Account" required />
                                <select id="bank_gl" v-model="bankForm.gl_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    <option value="">Select postable account…</option>
                                    <option v-for="account in (props.glAccounts as AccountOption[])" :key="account.value" :value="account.value">{{ account.label }}</option>
                                </select>
                                <InputError :message="bankForm.errors.gl_account_id" class="mt-1" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" v-model="bankForm.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                            Active
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showBankModal = false">Cancel</button>
                            <PrimaryButton :disabled="bankForm.processing">{{ editingBank ? 'Save Changes' : 'Create Bank Account' }}</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>