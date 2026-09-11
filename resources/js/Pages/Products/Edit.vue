<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ProductForm from './ProductForm.vue';

defineProps<{
    product: {
        id: number;
        sku: string;
        name: string;
        type: string;
        category_id: number | null;
        unit_id: number | null;
        purchase_price: string | number;
        sales_price: string | number;
        tax_rate_id: number | null;
        inventory_account_id: number | null;
        sales_account_id: number | null;
        purchase_account_id: number | null;
        cogs_account_id: number | null;
        track_inventory: boolean;
        low_stock_threshold: number | null;
        is_active: boolean;
    };
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
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Edit Product / Service" description="Update product details, pricing, and posting accounts." />
            <ProductForm
                :product="product"
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