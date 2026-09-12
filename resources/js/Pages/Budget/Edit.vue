<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BudgetForm from './BudgetForm.vue';

interface LineRow {
    id?: number;
    account_id: number;
    period_id: number;
    budgeted_amount: number | string;
}

defineProps<{
    fiscalYears: { value: number; label: string }[];
    accounts: { value: number; label: string }[];
    periods: { value: number; label: string; fiscal_year_id: number }[];
    initialLines: LineRow[];
    initialBudget: { name: string; fiscal_year_id: number; notes: string | null } | null;
    budget: { id: number };
}>();
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader title="Edit Budget" description="Update budget lines while the budget is still a draft." />

        <div class="mx-auto max-w-5xl">
            <BudgetForm
                :fiscal-years="fiscalYears"
                :accounts="accounts"
                :periods="periods"
                :initial-lines="initialLines"
                :initial-budget="initialBudget"
                :budget="budget"
            />
        </div>
    </AuthenticatedLayout>
</template>