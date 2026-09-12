<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CashBankTabs from '@/Components/CashBankTabs.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface MatchedTransaction {
    id: number;
    transaction_no: string;
    transaction_date: string;
    type: string;
    amount: number;
    account_label: string;
}

interface StatementLine {
    id: number;
    line_date: string;
    description: string | null;
    amount: number;
    is_reconciled: boolean;
    matched_transaction: MatchedTransaction | null;
}

interface UnmatchedTransaction {
    id: number;
    transaction_no: string;
    transaction_date: string;
    type: string;
    amount: number;
    gl_amount: number;
}

const props = defineProps<{
    import: {
        id: number;
        statement_month: string;
        bank_account: { id: number; account_name: string } | null;
        status: string;
        completed_at: string | null;
        lines: StatementLine[];
    };
    unmatchedTransactions: UnmatchedTransaction[];
}>();

const TYPE_LABELS: Record<string, string> = {
    cash_receipt: 'Cash Receipt',
    cash_payment: 'Cash Payment',
    bank_deposit: 'Bank Deposit',
    bank_withdrawal: 'Bank Withdrawal',
    bank_transfer: 'Bank Transfer',
    bank_charge: 'Bank Charge',
    bank_interest: 'Bank Interest',
};

const matchForms = ref<Record<number, string>>({});
const isCompleted = () => props.import.status === 'completed';

const autoMatch = () => {
    router.post(route('cash-bank.reconciliations.auto-match', props.import.id), {}, { preserveScroll: true });
};

const complete = () => {
    if (confirm('Complete this reconciliation? Transactions on or before this month will be locked.')) {
        router.post(route('cash-bank.reconciliations.complete', props.import.id), {}, { preserveScroll: true });
    }
};

const matchLine = (line: StatementLine) => {
    const transactionId = matchForms.value[line.id];
    if (!transactionId) {
        return;
    }
    router.post(route('cash-bank.reconciliations.match-line', { import: props.import.id, line: line.id }), {
        transaction_id: transactionId,
    }, { preserveScroll: true });
};

const unmatchLine = (line: StatementLine) => {
    router.post(route('cash-bank.reconciliations.unmatch-line', { import: props.import.id, line: line.id }), {}, { preserveScroll: true });
};

const setMatchValue = (lineId: number, event: Event) => {
    matchForms.value[lineId] = (event.target as HTMLSelectElement).value;
};

const matchedCount = () => props.import.lines.filter((line) => line.is_reconciled).length;
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Reconciliation Detail" description="Review statement lines, match them to system transactions, then complete to lock the period.">
                <template #actions>
                    <div v-if="!isCompleted()" class="flex gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" @click="autoMatch">
                            Auto-Match
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700" @click="complete">
                            Complete Reconciliation
                        </button>
                    </div>
                    <span v-else class="rounded-lg bg-green-50 px-4 py-2 text-sm font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300">
                        Completed
                    </span>
                </template>
            </PageHeader>

            <CashBankTabs active="reconciliation" />

            <div class="mb-4 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="font-medium text-gray-900 dark:text-white">{{ props.import.bank_account?.account_name }}</span>
                <span class="text-gray-500 dark:text-gray-400">{{ props.import.statement_month }}</span>
                <span class="text-gray-500 dark:text-gray-400">{{ matchedCount() }} / {{ props.import.lines.length }} lines matched</span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Statement Lines</h3>
                </div>
                <div v-if="props.import.lines.length === 0" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    No lines imported yet.
                </div>
                <table v-else class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Matched To</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="line in props.import.lines" :key="line.id" :class="{ 'bg-green-50/60 dark:bg-green-950/20': line.is_reconciled }">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ line.line_date }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ line.description || '—' }}</td>
                            <td class="px-4 py-3 font-medium" :class="line.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                                {{ formatMoney(line.amount) }}
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="line.matched_transaction" class="flex items-center gap-1.5">
                                    <AppIcon name="check" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                    <span class="text-xs text-gray-600 dark:text-gray-300">
                                        {{ line.matched_transaction.transaction_no }} • {{ line.matched_transaction.account_label }}
                                    </span>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <select v-if="!isCompleted()" :value="matchForms[line.id] ?? ''" class="max-w-[220px] rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" @change="setMatchValue(line.id, $event)">
                                        <option value="">Match transaction…</option>
                                        <option v-for="tx in props.unmatchedTransactions" :key="tx.id" :value="tx.id">
                                            {{ tx.transaction_no }} • {{ tx.transaction_date }} • {{ formatMoney(tx.amount) }}
                                        </option>
                                    </select>
                                    <button v-if="matchForms[line.id]" type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="matchLine(line)">Match</button>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="line.is_reconciled && !isCompleted()" type="button" class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" @click="unmatchLine(line)">
                                    Unmatch
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-right">
                <Link :href="route('cash-bank.reconciliations')" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Back to Reconciliations</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>