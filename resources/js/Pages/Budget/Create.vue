<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BudgetForm from './BudgetForm.vue';

defineProps<{
    fiscalYears: { value: number; label: string }[];
    accounts: { value: number; label: string }[];
    periods: { value: number; label: string; fiscal_year_id: number }[];
    initialLines: { account_id: number; period_id: number; budgeted_amount: number }[];
    initialBudget: { name: string; fiscal_year_id: number; notes: string | null } | null;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="New Budget"
            description="Set an annual operating budget per account and period. Post to freeze it for the fiscal year."
        />

        <div class="mx-auto max-w-5xl">
            <BudgetForm
                :fiscal-years="fiscalYears"
                :accounts="accounts"
                :periods="periods"
                :initial-lines="initialLines"
                :initial-budget="initialBudget"
            />
        </div>
    </AuthenticatedLayout>
</template>