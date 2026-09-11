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

interface LinePayload {
    product_id: number;
    description?: string | null;
    quantity: number | string;
    unit_price: number | string;
    discount_amount?: number | string | null;
    tax_rate_id?: number | null;
}

const props = defineProps<{
    invoice: {
        id: number;
        customer_id: number;
        invoice_date: string;
        due_date?: string | null;
        reference?: string | null;
        notes?: string | null;
    } | null;
    customers: CustomerOption[];
    products: ProductOption[];
    taxRates: TaxRateOption[];
    lines: LinePayload[];
}>();

const lines = props.lines.map((l) => ({
    product_id: l.product_id,
    description: l.description ?? '',
    quantity: String(l.quantity),
    unit_price: String(l.unit_price),
    discount_amount: String(l.discount_amount ?? '0'),
    tax_rate_id: (l.tax_rate_id ?? '') as number | '',
}));
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="Edit Draft Invoice"
            description="Change draft invoice details. A posted invoice can no longer be edited."
        />

        <InvoiceForm
            :invoice="invoice"
            :customers="customers"
            :products="products"
            :tax-rates="taxRates"
            :lines="lines"
            submit-label="Update Draft"
        />
    </AuthenticatedLayout>
</template>