<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FiscalYearForm from './FiscalYearForm.vue';

defineProps<{
    fiscalYear: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        is_active: boolean;
    };
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Edit Fiscal Year" description="Update fiscal year configuration." />
            <FiscalYearForm :fiscal-year="fiscalYear" />
        </div>
    </AuthenticatedLayout>
</template>