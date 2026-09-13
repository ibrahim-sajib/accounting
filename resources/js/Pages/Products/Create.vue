<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ProductForm from './ProductForm.vue';

defineProps<{
    defaults: { inventory: number | null; sales: number | null; purchase: number | null; cogs: number | null };
    accountOptions: { value: number; label: string }[];
    taxRateOptions: { value: number; label: string }[];
    categoryOptions: { value: number; label: string }[];
    unitOptions: { value: number; label: string }[];
    typeOptions: { value: string; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Create Product / Service" description="Add a new product or service to the catalog." />
            <ProductForm
                :defaults="defaults"
                :account-options="accountOptions"
                :tax-rate-options="taxRateOptions"
                :category-options="categoryOptions"
                :unit-options="unitOptions"
                :type-options="typeOptions"
            />
        </div>
    </AuthenticatedLayout>
</template>