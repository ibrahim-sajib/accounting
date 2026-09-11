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

interface Line {
    product_id: number | '';
    counted_qty: number;
}

defineProps<{
    adjustment: {
        id: number;
        adjustment_date: string;
        warehouse_id: number | '';
        reason: string;
        memo: string | null;
    };
    lines: Line[];
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
                title="Edit Stock Adjustment Draft"
                description="Counts will be re-checked against current stock when this draft is posted."
            />
            <AdjustmentForm
                action="edit"
                :submit-url="route('stock-adjustments.update', adjustment.id)"
                :warehouses="warehouses"
                :products="products"
                :system-qty="systemQty"
                :reasons="reasons"
                :today="today"
                :initial="{
                    adjustment_date: adjustment.adjustment_date,
                    warehouse_id: adjustment.warehouse_id,
                    reason: adjustment.reason,
                    memo: adjustment.memo ?? '',
                    lines: lines.map((l) => ({ product_id: l.product_id, counted_qty: String(l.counted_qty) })),
                }"
            />
        </div>
    </AuthenticatedLayout>
</template>