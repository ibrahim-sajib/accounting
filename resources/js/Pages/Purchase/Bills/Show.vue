<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface AccountOption {
    value: number;
    label: string;
}

interface SupplierBrief {
    id: number;
    code?: string | null;
    name: string;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    tax_no?: string | null;
}

interface BillLine {
    id: number;
    product_id: number;
    product_name?: string | null;
    product_sku?: string | null;
    description?: string | null;
    quantity: number;
    unit_cost: number;
    discount_amount: number;
    tax_rate_name?: string | null;
    tax_rate_percent: number;
    tax_amount: number;
    line_total: number;
}

interface Payment {
    id: number;
    payment_no: string | null;
    payment_date: string;
    reference?: string | null;
    memo?: string | null;
    account_name?: string | null;
    amount: number;
    journal_id?: number | null;
    journal_no?: string | null;
}

interface BillShow {
    id: number;
    bill_no: string | null;
    bill_date: string;
    due_date: string | null;
    reference?: string | null;
    notes?: string | null;
    status: string;
    paid_state: string | null;
    overdue: boolean;
    subtotal: number;
    discount_amount: number;
    tax_amount: number;
    total: number;
    amount_paid: number;
    balance_due: number;
    posted_at?: string | null;
    supplier: SupplierBrief | null;
    lines: BillLine[];
    payments: Payment[];
    journal_id?: number | null;
    journal_no?: string | null;
}

const page = usePage();
const props = defineProps<{
    bill: BillShow;
    accounts: AccountOption[];
    today?: string;
}>();

const isDraft = computed(() => props.bill.status === 'draft');
const isPosted = computed(() => props.bill.status === 'posted');
const balanceDue = computed(() => Math.max(Number(props.bill.balance_due ?? 0), 0));

const canPost = computed(() => page.props.auth.permissions.includes('purchase.post') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('purchase.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('purchase.delete') || page.props.auth.user.is_super_admin);
const canRecordPayment = computed(() => page.props.auth.permissions.includes('payment.create') || page.props.auth.user.is_super_admin);

const showPayment = ref(false);

const paymentForm = useForm({
    payment_date: props.today ?? new Date().toISOString().slice(0, 10),
    account_id: props.accounts[0]?.value ?? ('' as number | ''),
    amount: String(balanceDue.value),
    reference: '',
    memo: '',
});

const openPayment = () => {
    paymentForm.clearErrors();
    paymentForm.payment_date = props.today ?? new Date().toISOString().slice(0, 10);
    paymentForm.amount = String(balanceDue.value);
    showPayment.value = true;
};

const recordPayment = () => {
    paymentForm.post(route('purchase.bills.pay', props.bill.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPayment.value = false;
        },
    });
};

const postBill = () => {
    if (confirm('Post this bill? A journal (Inventory/Expense | Input Tax | Accounts Payable) will be posted and the bill becomes uneditable.')) {
        router.post(route('purchase.bills.post', props.bill.id), {}, { preserveScroll: true });
    }
};

const deleteBill = () => {
    if (confirm(`Delete draft bill from ${props.bill.supplier?.name ?? 'supplier'}?`)) {
        router.delete(route('purchase.bills.destroy', props.bill.id));
    }
};

const paymentState = computed(() => {
    if (!isPosted.value) return 'draft';
    if (props.bill.overdue) return 'overdue';
    return props.bill.paid_state ?? 'unpaid';
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                :title="bill.bill_no ?? 'Draft Bill'"
                :description="`From ${bill.supplier?.name ?? '—'} on ${formatDate(bill.bill_date)}`"
            >
                <template #actions>
                    <Link
                        v-if="isDraft && canUpdate"
                        :href="route('purchase.bills.edit', bill.id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="isDraft && canDelete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 dark:border-red-900 dark:bg-gray-900"
                        @click="deleteBill"
                    >
                        Delete
                    </button>
                    <button
                        v-if="isDraft && canPost"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="postBill"
                    >
                        Post Bill
                    </button>
                    <button
                        v-if="isPosted && canRecordPayment && balanceDue > 0"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700"
                        @click="openPayment"
                    >
                        <AppIcon name="payment" class="h-4 w-4" />
                        Record Payment
                    </button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400">Bill #</div>
                                <div class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ bill.bill_no ?? 'Not numbered yet' }}</div>
                                <div class="mt-2"><StatusBadge :status="paymentState" /></div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase tracking-wide text-gray-400">Balance due</div>
                                <div class="font-mono text-2xl font-bold" :class="balanceDue > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                    {{ formatMoney(balanceDue) }}
                                </div>
                            </div>
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-gray-400">Dated</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ formatDate(bill.bill_date) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Due date</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ bill.due_date ? formatDate(bill.due_date) : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Reference</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ bill.reference ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Posting journal</dt>
                                <dd class="font-medium">
                                    <Link
                                        v-if="bill.journal_id"
                                        :href="route('journals.show', bill.journal_id)"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{ bill.journal_no ?? 'Journal' }}
                                    </Link>
                                    <span v-else class="text-gray-400">Not posted</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Description</th>
                                    <th class="px-4 py-3 text-right font-semibold">Qty</th>
                                    <th class="px-4 py-3 text-right font-semibold">Cost</th>
                                    <th class="px-4 py-3 text-right font-semibold">Tax</th>
                                    <th class="px-4 py-3 text-right font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="line in bill.lines" :key="line.id">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ line.description || line.product_name || 'Item' }}</div>
                                        <div v-if="line.product_name && line.product_name !== line.description" class="text-xs text-gray-400">
                                            {{ line.product_name }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ line.quantity }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ formatMoney(line.unit_cost) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">
                                        <template v-if="Number(line.tax_rate_percent) > 0">
                                            {{ formatMoney(line.tax_amount) }} <span class="text-xs text-gray-400">({{ line.tax_rate_percent }}%)</span>
                                        </template>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(line.line_total) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="text-sm">
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <td class="px-4 py-3 text-right" colspan="4">Subtotal</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(bill.subtotal) }}</td>
                                </tr>
                                <tr v-if="Number(bill.discount_amount) > 0" class="text-gray-500 dark:text-gray-400">
                                    <td class="px-4 py-3 text-right" colspan="4">Discount</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">−{{ formatMoney(bill.discount_amount) }}</td>
                                </tr>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <td class="px-4 py-3 text-right" colspan="4">Input Tax</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(bill.tax_amount) }}</td>
                                </tr>
                                <tr class="border-t border-gray-200 dark:border-gray-800">
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-200" colspan="4">Total</td>
                                    <td class="px-4 py-3 text-right font-mono text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(bill.total) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        <div v-if="bill.notes" class="border-t border-gray-100 px-4 py-3 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            {{ bill.notes }}
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Supplier</h2>
                        <div class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <p class="font-medium text-gray-900 dark:text-white">{{ bill.supplier?.name }}</p>
                            <p v-if="bill.supplier?.code" class="font-mono text-xs text-gray-400">{{ bill.supplier.code }}</p>
                            <p v-if="bill.supplier?.email">{{ bill.supplier.email }}</p>
                            <p v-if="bill.supplier?.phone">{{ bill.supplier.phone }}</p>
                            <p v-if="bill.supplier?.address" class="text-xs text-gray-400">{{ bill.supplier.address }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Payments</h2>
                        <p v-if="bill.payments.length === 0" class="mt-3 text-sm text-gray-400">
                            No payments recorded yet.
                        </p>
                        <ul v-else class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="payment in bill.payments" :key="payment.id" class="flex items-center justify-between py-2.5 text-sm">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ payment.payment_no ?? 'Payment' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatDate(payment.payment_date) }}
                                        <template v-if="payment.account_name"> · {{ payment.account_name }}</template>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono text-gray-900 dark:text-white">{{ formatMoney(payment.amount) }}</div>
                                    <Link
                                        v-if="payment.journal_id"
                                        :href="route('journals.show', payment.journal_id)"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{ payment.journal_no ?? 'Journal' }}
                                    </Link>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showPayment" max-width="md" @close="showPayment = false">
            <form class="p-6" @submit.prevent="recordPayment">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Record Payment</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Balance due {{ formatMoney(balanceDue) }}. A posted payment (journal: AP | Bank/Cash) will be created.
                </p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="payment_date" value="Payment date" />
                        <TextInput id="payment_date" v-model="paymentForm.payment_date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="paymentForm.errors.payment_date" />
                    </div>
                    <div>
                        <InputLabel for="pay_account" value="Pay from account" />
                        <select
                            id="pay_account"
                            v-model="paymentForm.account_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            required
                        >
                            <option value="">Select account…</option>
                            <option v-for="account in accounts" :key="account.value" :value="account.value">{{ account.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="paymentForm.errors.account_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="pay_amount" value="Amount" />
                        <div class="flex items-center gap-2">
                            <TextInput
                                id="pay_amount"
                                v-model="paymentForm.amount"
                                type="number"
                                step="0.0001"
                                min="0"
                                :max="String(balanceDue)"
                                class="mt-1 block w-full"
                                required
                            />
                            <button
                                type="button"
                                class="mt-1 shrink-0 rounded-md bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200"
                                @click="paymentForm.amount = String(balanceDue)"
                            >
                                Full
                            </button>
                        </div>
                        <InputError class="mt-2" :message="paymentForm.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="pay_reference" value="Reference (optional)" />
                        <TextInput id="pay_reference" v-model="paymentForm.reference" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="paymentForm.errors.reference" />
                    </div>
                    <div>
                        <InputLabel for="pay_memo" value="Memo (optional)" />
                        <TextInput id="pay_memo" v-model="paymentForm.memo" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="paymentForm.errors.memo" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showPayment = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="paymentForm.processing">
                        Record Payment
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>