<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface Category {
    id: number;
    name: string;
}

interface TaxRate {
    id: number;
    name: string;
    rate_percent: number | string;
}

interface Supplier {
    id: number;
    name: string;
}

interface CashAccount {
    id: number;
    name: string;
}

interface BankAccount {
    id: number;
    name: string;
}

interface ExpenseShow {
    id: number;
    expense_no: string | null;
    expense_date: string;
    payee: string;
    amount: number | string;
    tax_amount: number | string;
    payment_method: string;
    reference: string | null;
    notes: string | null;
    status: string;
    is_recurring: boolean;
    recurrence_frequency: string | null;
    next_generation_date: string | null;
    posted_at: string | null;
    category: Category | null;
    taxRate: TaxRate | null;
    supplier: Supplier | null;
    cashAccount: CashAccount | null;
    bankAccount: BankAccount | null;
    journal: { id: number; journal_no: string | null } | null;
}

const page = usePage();
const props = defineProps<{ expense: ExpenseShow }>();

const isDraft = computed(() => props.expense.status === 'draft');

const canPost = computed(() => page.props.auth.permissions.includes('expense.post') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('expense.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('expense.delete') || page.props.auth.user.is_super_admin);

const total = computed(() => Number(props.expense.amount) + Number(props.expense.tax_amount));
const taxRateLabel = computed(() => {
    if (!props.expense.taxRate) return null;
    return `${props.expense.taxRate.name} (${props.expense.taxRate.rate_percent}%)`;
});

const postExpense = () => {
    if (confirm('Post this expense? A journal (Expense Account | Input Tax | Cash/Bank/Payable) will be posted and the expense becomes uneditable.')) {
        router.post(route('expenses.post', props.expense.id), {}, { preserveScroll: true });
    }
};

const deleteExpense = () => {
    if (confirm(`Delete this draft expense from ${props.expense.payee}?`)) {
        router.delete(route('expenses.destroy', props.expense.id));
    }
};

const methodLabel = computed(() => {
    const map: Record<string, string> = { cash: 'Cash', bank: 'Bank', payable: 'Payable' };
    return map[props.expense.payment_method] ?? props.expense.payment_method;
});
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                :title="expense.expense_no ?? 'Draft Expense'"
                :description="`${expense.payee} · Paid by ${methodLabel} on ${formatDate(expense.expense_date)}`"
            >
                <template #actions>
                    <Link
                        v-if="isDraft && canUpdate"
                        :href="route('expenses.edit', expense.id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="isDraft && canDelete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 dark:border-red-900 dark:bg-gray-900"
                        @click="deleteExpense"
                    >
                        Delete
                    </button>
                    <button
                        v-if="isDraft && canPost"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="postExpense"
                    >
                        Post Expense
                    </button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400">Expense #</div>
                                <div class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ expense.expense_no ?? 'Not numbered yet' }}</div>
                                <div class="mt-2"><StatusBadge :status="expense.status" /></div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase tracking-wide text-gray-400">Total</div>
                                <div class="font-mono text-2xl font-bold text-gray-900 dark:text-white">{{ formatMoney(total) }}</div>
                            </div>
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-gray-400">Category</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ expense.category?.name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Payee</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ expense.payee }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Reference</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ expense.reference ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Posting journal</dt>
                                <dd class="font-medium">
                                    <Link
                                        v-if="expense.journal"
                                        :href="route('journals.show', expense.journal.id)"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{ expense.journal.journal_no ?? 'Journal' }}
                                    </Link>
                                    <span v-else class="text-gray-400">Not posted</span>
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-800/50">
                            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                                <span>Amount (net of tax)</span>
                                <span class="font-mono">{{ formatMoney(expense.amount) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                                <span>Input tax <template v-if="taxRateLabel">· {{ taxRateLabel }}</template></span>
                                <span class="font-mono">{{ formatMoney(expense.tax_amount) }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between border-t border-gray-200 pt-2 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-white">
                                <span>Total</span>
                                <span class="font-mono">{{ formatMoney(total) }}</span>
                            </div>
                        </div>

                        <div v-if="expense.notes" class="mt-4 border-t border-gray-100 px-0 pt-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            {{ expense.notes }}
                        </div>
                    </div>

                    <div v-if="expense.is_recurring" class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950/40">
                        <div class="flex items-center gap-2 text-sm font-semibold text-indigo-700 dark:text-indigo-300">
                            <AppIcon name="calendar" class="h-4 w-4" />
                            Recurring expense
                        </div>
                        <p class="mt-1 text-sm text-indigo-600 dark:text-indigo-400">
                            A new draft copy is generated every {{ expense.recurrence_frequency ?? 'cycle' }}.
                            <template v-if="expense.next_generation_date">Next run {{ formatDate(expense.next_generation_date) }}.</template>
                        </p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Payment</h2>
                        <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Method</span>
                                <span class="capitalize font-medium text-gray-900 dark:text-white">{{ methodLabel }}</span>
                            </div>
                            <div v-if="expense.payment_method === 'cash' && expense.cashAccount" class="flex items-center justify-between">
                                <span class="text-gray-400">Cash account</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ expense.cashAccount.name }}</span>
                            </div>
                            <div v-if="expense.payment_method === 'bank' && expense.bankAccount" class="flex items-center justify-between">
                                <span class="text-gray-400">Bank account</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ expense.bankAccount.name }}</span>
                            </div>
                            <div v-if="expense.payment_method === 'payable' && expense.supplier" class="flex items-center justify-between">
                                <span class="text-gray-400">Supplier</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ expense.supplier.name }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Audit</h2>
                        <div class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Posted at</span>
                                <span>{{ expense.posted_at ? formatDate(expense.posted_at) : '—' }}</span>
                            </div>
                            <p class="pt-2 text-xs text-gray-400">
                                <Link
                                    v-if="expense.journal"
                                    :href="route('journals.show', expense.journal.id)"
                                    class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                >
                                    View posting journal
                                </Link>
                                <template v-else>The expense will earn its number when posted.</template>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>