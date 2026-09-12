<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExpenseForm from './ExpenseForm.vue';

defineProps<{
    categories: { value: number; label: string; expense_account_id: number | null }[];
    taxRates: { value: number; label: string; rate_percent: number }[];
    cashAccounts: { value: number; label: string }[];
    bankAccounts: { value: number; label: string }[];
    suppliers: { value: number; label: string }[];
    paymentMethods: { value: string; label: string }[];
    defaultCashAccountId?: number | null;
    defaultBankAccountId?: number | null;
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="New Expense"
            description="Record an operating expense. Post it afterward to post the journal (Expense Account | Input Tax | Cash/Bank/Payable)."
        />

        <div class="mx-auto max-w-3xl">
            <ExpenseForm
                :categories="categories"
                :tax-rates="taxRates"
                :cash-accounts="cashAccounts"
                :bank-accounts="bankAccounts"
                :suppliers="suppliers"
                :payment-methods="paymentMethods"
                :default-cash-account-id="defaultCashAccountId"
                :default-bank-account-id="defaultBankAccountId"
                :today="today"
            />
        </div>
    </AuthenticatedLayout>
</template>