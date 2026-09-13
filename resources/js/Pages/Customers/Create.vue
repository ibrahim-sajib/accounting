<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CustomerForm from './CustomerForm.vue';

defineProps<{
    defaultArAccountId: number | null;
    accountOptions: { value: number; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Create Customer" description="Add a new customer to the current company." />
            <CustomerForm :default-ar-account-id="defaultArAccountId" :account-options="accountOptions" />
        </div>
    </AuthenticatedLayout>
</template>