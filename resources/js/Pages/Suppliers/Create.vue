<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SupplierForm from './SupplierForm.vue';

defineProps<{
    defaultApAccountId: number | null;
    accountOptions: { value: number; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Create Supplier" description="Add a new supplier to the current company." />
            <SupplierForm :default-ap-account-id="defaultApAccountId" :account-options="accountOptions" />
        </div>
    </AuthenticatedLayout>
</template>