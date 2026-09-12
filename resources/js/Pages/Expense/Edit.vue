<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExpenseForm from './ExpenseForm.vue';

interface CategoryOption {
    value: number;
    label: string;
    expense_account_id: number | null;
}

interface TaxRateOption {
    value: number;
    label: string;
    rate_percent: number;
}

interface AccountOption {
    value: number;
    label: string;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface Expense {
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
}

defineProps<{
    expense: Expense;
    categories: CategoryOption[];
    taxRates: TaxRateOption[];
    cashAccounts: AccountOption[];
    bankAccounts: AccountOption[];
    suppliers: AccountOption[];
    paymentMethods: PaymentMethodOption[];
    defaultCashAccountId?: number | null;
    defaultBankAccountId?: number | null;
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="Edit Expense"
            :description="expense.payee ? `Edit ${expense.payee} expense draft` : 'Edit expense draft'"
        />

        <div class="mx-auto max-w-3xl">
            <ExpenseForm
                :expense="expense"
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