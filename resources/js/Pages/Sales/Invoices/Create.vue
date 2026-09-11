<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InvoiceForm from './InvoiceForm.vue';

interface CustomerOption {
    value: number;
    label: string;
    credit_limit: number;
    payment_terms_days: number;
    outstanding: number;
}

interface ProductOption {
    value: number;
    label: string;
    type: string;
    sales_price: number;
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
    customers: CustomerOption[];
    products: ProductOption[];
    taxRates: TaxRateOption[];
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="New Sales Invoice"
            :description="`Issue a new invoice to a customer. Post it afterward to post the journal (AR | Revenue | Output Tax${products.some((p) => p.type === 'product') ? ' | COGS / Inventory' : ''}).`"
        />

        <InvoiceForm
            :customers="customers"
            :products="products"
            :tax-rates="taxRates"
            :today="today"
        />
    </AuthenticatedLayout>
</template>