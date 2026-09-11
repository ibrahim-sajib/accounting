<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SupplierForm from './SupplierForm.vue';

defineProps<{
    supplier: {
        id: number;
        code: string;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        tax_no: string | null;
        payment_terms_days: number;
        opening_balance: string | number;
        ap_account_id: number | null;
        is_active: boolean;
    };
    defaultApAccountId: number | null;
    accountOptions: { value: number; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Edit Supplier" description="Update supplier information and terms." />
            <SupplierForm :supplier="supplier" :default-ap-account-id="defaultApAccountId" :account-options="accountOptions" />
        </div>
    </AuthenticatedLayout>
</template>