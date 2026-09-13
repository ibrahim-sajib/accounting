<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CompanyForm from './CompanyForm.vue';

const props = defineProps<{
    statusOptions: { value: string; label: string }[];
    accountingBasisOptions: { value: string; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Create Company" description="Set up a new organization and its base configuration." />
            <CompanyForm
                :status-options="statusOptions"
                :accounting-basis-options="accountingBasisOptions"
            />
        </div>
    </AuthenticatedLayout>
</template>