<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BillForm from './BillForm.vue';

interface SupplierOption {
    value: number;
    label: string;
    payment_terms_days: number;
    outstanding: number;
}

interface ProductOption {
    value: number;
    label: string;
    type: string;
    purchase_price: number;
    tax_rate_id: number | null;
    unit?: string | null;
}

interface TaxRateOption {
    value: number;
    label: string;
    rate_percent: number;
    is_inclusive: boolean;
}

defineProps<{
    suppliers: SupplierOption[];
    products: ProductOption[];
    taxRates: TaxRateOption[];
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="New Purchase Bill"
            description="Receive a bill from a supplier. Post it afterward to post the journal (Inventory/Expense | Input Tax | Accounts Payable)."
        />

        <BillForm
            :suppliers="suppliers"
            :products="products"
            :tax-rates="taxRates"
            :today="today"
        />
    </AuthenticatedLayout>
</template>