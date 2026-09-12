<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AssetForm from './AssetForm.vue';

interface AssetShape {
    id: number;
    category_id: number;
    asset_code: string;
    name: string;
    acquisition_date: string;
    acquisition_cost: number | string;
    useful_life_months: number;
    method: string;
    location: string | null;
    acquisition_method: string;
    cash_account_id: number | null;
    bank_account_id: number | null;
    supplier_id: number | null;
}

defineProps<{
    asset: AssetShape;
    categories: { value: number; label: string; default_method: string; default_useful_life_months: number }[];
    cashAccounts: { value: number; label: string }[];
    bankAccounts: { value: number; label: string }[];
    suppliers: { value: number; label: string }[];
    acquisitionMethods: { value: string; label: string }[];
    depreciationMethods: { value: string; label: string }[];
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="Edit Fixed Asset"
            description="Only draft assets can be edited. Capitalize to activate it."
        />

        <div class="mx-auto max-w-3xl">
            <AssetForm
                :asset="asset"
                :categories="categories"
                :cash-accounts="cashAccounts"
                :bank-accounts="bankAccounts"
                :suppliers="suppliers"
                :acquisition-methods="acquisitionMethods"
                :depreciation-methods="depreciationMethods"
                :today="today"
            />
        </div>
    </AuthenticatedLayout>
</template>