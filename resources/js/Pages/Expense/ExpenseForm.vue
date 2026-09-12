<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface Option {
    value: number | string;
    label: string;
    rate_percent?: number;
}

interface CategoryOption extends Option {
    expense_account_id: number | null;
}

const props = defineProps<{
    categories: CategoryOption[];
    taxRates: Option[];
    cashAccounts: Option[];
    bankAccounts: Option[];
    suppliers: Option[];
    paymentMethods: { value: string; label: string }[];
    defaultCashAccountId?: number | null;
    defaultBankAccountId?: number | null;
    today?: string;
    expense?: {
        id: number;
        category_id: number;
        payee: string;
        expense_date: string;
        amount: number | string;
        tax_rate_id: number | null;
        payment_method: string;
        cash_account_id: number | null;
        bank_account_id: number | null;
        supplier_id: number | null;
        reference: string | null;
        notes: string | null;
        is_recurring: boolean;
        recurrence_frequency: string | null;
        next_generation_date: string | null;
    } | null;
}>();

const isEdit = !!props.expense;
const today = props.today ?? new Date().toISOString().slice(0, 10);

const form = useForm({
    category_id: props.expense?.category_id ?? ('' as number | ''),
    payee: props.expense?.payee ?? '',
    expense_date: props.expense?.expense_date ?? today,
    amount: String(props.expense?.amount ?? ''),
    tax_rate_id: props.expense?.tax_rate_id ?? ('' as number | ''),
    payment_method: props.expense?.payment_method ?? 'cash',
    cash_account_id: props.expense?.cash_account_id ?? (props.defaultCashAccountId ?? ('' as number | '')),
    bank_account_id: props.expense?.bank_account_id ?? (props.defaultBankAccountId ?? ('' as number | '')),
    supplier_id: props.expense?.supplier_id ?? ('' as number | ''),
    reference: props.expense?.reference ?? '',
    notes: props.expense?.notes ?? '',
    is_recurring: props.expense?.is_recurring ?? false,
    recurrence_frequency: props.expense?.recurrence_frequency ?? 'monthly',
    next_generation_date: '',
});

const isRecurring = computed(() => Boolean(form.is_recurring));
const amount = computed(() => {
    const n = parseFloat(String(form.amount));
    return Number.isFinite(n) ? n : 0;
});

const selectedTaxRate = computed(() => {
    const v = form.tax_rate_id;
    if (v === '' || v === null) return null;
    return props.taxRates.find((t) => String(t.value) === String(v)) ?? null;
});

const taxRatePercent = computed(() => selectedTaxRate.value?.rate_percent ?? 0);

const taxAmount = computed(() => amount.value * taxRatePercent.value / 100);
const total = computed(() => amount.value + taxAmount.value);

const requiresBank = computed(() => form.payment_method === 'bank');
const requiresCash = computed(() => form.payment_method === 'cash');
const requiresPayable = computed(() => form.payment_method === 'payable');

const submit = () => {
    if (isEdit) {
        form.put(route('expenses.update', props.expense!.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('expenses.store'), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Expense Details</h2>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="expense_category" value="Expense Category *" />
                    <select
                        id="expense_category"
                        v-model="form.category_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="">Select category…</option>
                        <option v-for="category in categories" :key="category.value" :value="category.value">{{ category.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.category_id" />
                </div>

                <div>
                    <InputLabel for="expense_payee" value="Payee *" />
                    <TextInput
                        id="expense_payee"
                        v-model="form.payee"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="e.g. Utility Company, Landlord…"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.payee" />
                </div>

                <div>
                    <InputLabel for="expense_date" value="Expense date *" />
                    <TextInput id="expense_date" v-model="form.expense_date" type="date" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.expense_date" />
                </div>

                <div>
                    <InputLabel for="expense_amount" value="Amount (net of tax) *" />
                    <TextInput
                        id="expense_amount"
                        v-model="form.amount"
                        type="number"
                        step="0.0001"
                        min="0"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>

                <div>
                    <InputLabel for="expense_tax" value="Tax rate" />
                    <select
                        id="expense_tax"
                        v-model="form.tax_rate_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">No tax</option>
                        <option v-for="rate in taxRates" :key="rate.value" :value="rate.value">{{ rate.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.tax_rate_id" />
                </div>

                <div>
                    <InputLabel for="expense_method" value="Payment method *" />
                    <select
                        id="expense_method"
                        v-model="form.payment_method"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.payment_method" />
                </div>

                <div v-if="requiresCash">
                    <InputLabel for="expense_cash" value="Cash account *" />
                    <select
                        id="expense_cash"
                        v-model="form.cash_account_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="">Select cash account…</option>
                        <option v-for="account in cashAccounts" :key="account.value" :value="account.value">{{ account.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.cash_account_id" />
                </div>

                <div v-if="requiresBank">
                    <InputLabel for="expense_bank" value="Bank account *" />
                    <select
                        id="expense_bank"
                        v-model="form.bank_account_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="">Select bank account…</option>
                        <option v-for="account in bankAccounts" :key="account.value" :value="account.value">{{ account.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.bank_account_id" />
                </div>

                <div v-if="requiresPayable">
                    <InputLabel for="expense_supplier" value="Supplier (payable) *" />
                    <select
                        id="expense_supplier"
                        v-model="form.supplier_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="">Select supplier…</option>
                        <option v-for="supplier in suppliers" :key="supplier.value" :value="supplier.value">{{ supplier.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.supplier_id" />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Payable expenses behave like supplier bills — the credit goes to Accounts Payable.
                    </p>
                </div>

                <div>
                    <InputLabel for="expense_reference" value="Reference (optional)" />
                    <TextInput id="expense_reference" v-model="form.reference" type="text" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.reference" />
                </div>
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800/50">
                <div class="flex items-center justify-between text-gray-600 dark:text-gray-300">
                    <span>Amount</span>
                    <span class="font-mono">{{ formatMoney(amount) }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between text-gray-600 dark:text-gray-300">
                    <span>Tax <template v-if="taxRatePercent > 0">({{ taxRatePercent }}%)</template></span>
                    <span class="font-mono">{{ formatMoney(taxAmount) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900 dark:border-gray-700 dark:text-white">
                    <span>Total</span>
                    <span class="font-mono">{{ formatMoney(total) }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Recurring</h2>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Create an automatic draft copy on a schedule. Copies are never posted automatically.
                    </p>
                </div>
                <label class="inline-flex cursor-pointer items-center">
                    <input v-model="form.is_recurring" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-200">Make Recurring</span>
                </label>
            </div>

            <div v-if="isRecurring" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="recurrence_frequency" value="Frequency" />
                    <select
                        id="recurrence_frequency"
                        v-model="form.recurrence_frequency"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.recurrence_frequency" />
                </div>

                <div>
                    <InputLabel for="next_generation_date" value="Next run date" />
                    <TextInput
                        id="next_generation_date"
                        v-model="form.next_generation_date"
                        type="date"
                        class="mt-1 block w-full"
                        :min="form.expense_date"
                    />
                    <InputError class="mt-2" :message="form.errors.next_generation_date" />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Leave blank to use one schedule step from the expense date.</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <InputLabel for="expense_notes" value="Notes (optional)" />
            <textarea
                id="expense_notes"
                v-model="form.notes"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
            />
            <InputError class="mt-2" :message="form.errors.notes" />
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link
                :href="isEdit ? route('expenses.show', expense!.id) : route('expenses.index')"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ isEdit ? 'Save Changes' : 'Create Expense' }}
            </PrimaryButton>
        </div>
    </form>
</template>