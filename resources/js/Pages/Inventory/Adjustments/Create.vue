<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AdjustmentForm from '@/Pages/Inventory/Adjustments/AdjustmentForm.vue';

interface WarehouseOption {
    value: number;
    label: string;
}

interface ProductOption {
    value: number;
    label: string;
    unit?: string | null;
}

interface ReasonOption {
    value: string;
    label: string;
}

defineProps<{
    warehouses: WarehouseOption[] | null;
    products: ProductOption[] | null;
    systemQty: Record<string, string>;
    reasons: ReasonOption[] | null;
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="New Stock Adjustment"
                description="Count differences are posted to the Inventory and Inventory Adjustment Expense accounts."
            />
            <AdjustmentForm
                action="create"
                :submit-url="route('stock-adjustments.store')"
                :warehouses="warehouses"
                :products="products"
                :system-qty="systemQty"
                :reasons="reasons"
                :today="today"
            />
        </div>
    </AuthenticatedLayout>
</template>