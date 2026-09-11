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

interface LinePayload {
    product_id: number;
    description?: string | null;
    quantity: number | string;
    unit_cost: number | string;
    discount_amount?: number | string | null;
    tax_rate_id?: number | null;
}

const props = defineProps<{
    bill: {
        id: number;
        supplier_id: number;
        bill_date: string;
        due_date?: string | null;
        reference?: string | null;
        notes?: string | null;
    } | null;
    suppliers: SupplierOption[];
    products: ProductOption[];
    taxRates: TaxRateOption[];
    lines: LinePayload[];
}>();

const lines = props.lines.map((l) => ({
    product_id: l.product_id,
    description: l.description ?? '',
    quantity: String(l.quantity),
    unit_cost: String(l.unit_cost),
    discount_amount: String(l.discount_amount ?? '0'),
    tax_rate_id: (l.tax_rate_id ?? '') as number | '',
}));
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="Edit Draft Bill"
            description="Change draft bill details. A posted bill can no longer be edited."
        />

        <BillForm
            :bill="bill"
            :suppliers="suppliers"
            :products="products"
            :tax-rates="taxRates"
            :lines="lines"
            submit-label="Update Draft"
        />
    </AuthenticatedLayout>
</template>