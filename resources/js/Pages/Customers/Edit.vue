<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CustomerForm from './CustomerForm.vue';

defineProps<{
    customer: {
        id: number;
        code: string;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        tax_no: string | null;
        credit_limit: string | number;
        payment_terms_days: number;
        opening_balance: string | number;
        ar_account_id: number | null;
        is_active: boolean;
    };
    defaultArAccountId: number | null;
    accountOptions: { value: number; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Edit Customer" description="Update customer information and terms." />
            <CustomerForm :customer="customer" :default-ar-account-id="defaultArAccountId" :account-options="accountOptions" />
        </div>
    </AuthenticatedLayout>
</template>