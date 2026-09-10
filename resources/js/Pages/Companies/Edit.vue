<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CompanyForm from './CompanyForm.vue';

const props = defineProps<{
    company: {
        id: number;
        name: string;
        legal_name: string | null;
        country_code: string | null;
        currency_code: string | null;
        tax_registration_no: string | null;
        vat_registration_no: string | null;
        accounting_basis: string;
        status: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        city: string | null;
        state: string | null;
        zip_code: string | null;
        fiscal_year_start: string | null;
        fiscal_year_end: string | null;
    };
    statusOptions: { value: string; label: string }[];
    accountingBasisOptions: { value: string; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Edit Company" description="Update organization configuration." />
            <CompanyForm
                :company="company"
                :status-options="statusOptions"
                :accounting-basis-options="accountingBasisOptions"
            />
        </div>
    </AuthenticatedLayout>
</template>