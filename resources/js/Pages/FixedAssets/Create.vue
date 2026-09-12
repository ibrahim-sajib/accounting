<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AssetForm from './AssetForm.vue';

defineProps<{
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
            title="New Fixed Asset"
            description="Register an asset as a draft, then capitalize it to book Fixed Asset Dr | Cash/Bank/AP Cr."
        />

        <div class="mx-auto max-w-3xl">
            <AssetForm
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