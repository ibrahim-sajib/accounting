<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CashBankTabs from '@/Components/CashBankTabs.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface CashAccount {
    id: number;
    name: string;
    gl_account_code: string;
    balance: number;
    is_active: boolean;
}

interface BankAccount {
    id: number;
    account_name: string;
    account_no: string | null;
    bank_name: string;
    gl_account_code: string;
    balance: number;
    is_active: boolean;
}

interface RecentTransaction {
    id: number;
    transaction_no: string;
    transaction_type: string;
    transaction_date: string;
    amount: number;
    cash_account: string | null;
    bank_account: string | null;
    reference: string | null;
}

defineProps<{
    cashAccounts: CashAccount[];
    bankAccounts: BankAccount[];
    recentTransactions: RecentTransaction[];
    totalCash: number;
    totalBank: number;
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
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Cash & Bank" description="Cash and bank balances, direct receipts/payments, deposits, transfers and reconciliation." />

            <CashBankTabs active="overview" />

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <AppIcon name="bank" class="h-4 w-4" />
                        Total Cash
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatMoney(totalCash) }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <AppIcon name="bank" class="h-4 w-4" />
                        Total Bank
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatMoney(totalBank) }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash & Bank</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatMoney(totalCash + totalBank) }}</div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Cash Accounts</h3>
                        <Link :href="route('cash-bank.accounts')" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Manage</Link>
                    </div>
                    <div v-if="cashAccounts.length === 0" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        No cash accounts yet.
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="account in cashAccounts" :key="account.id" class="flex items-center justify-between px-5 py-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ account.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ account.gl_account_code }}</div>
                            </div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(account.balance) }}</div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Bank Accounts</h3>
                        <Link :href="route('cash-bank.accounts')" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Manage</Link>
                    </div>
                    <div v-if="bankAccounts.length === 0" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        No bank accounts yet.
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="account in bankAccounts" :key="account.id" class="flex items-center justify-between px-5 py-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ account.account_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ account.bank_name }}<template v-if="account.account_no"> • {{ account.account_no }}</template>
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(account.balance) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Recent Transactions</h3>
                </div>
                <div v-if="recentTransactions.length === 0" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    No transactions yet. Head to the Transactions tab to record a cash receipt or bank deposit.
                </div>
                <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="tx in recentTransactions" :key="tx.id" class="flex items-center justify-between px-5 py-3">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ TYPE_LABELS[tx.transaction_type] ?? tx.transaction_type }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ tx.transaction_no }} • {{ formatDate(tx.transaction_date) }}
                                <template v-if="tx.cash_account"> • {{ tx.cash_account }}</template>
                                <template v-if="tx.bank_account"> • {{ tx.bank_account }}</template>
                            </div>
                        </div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(tx.amount) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>