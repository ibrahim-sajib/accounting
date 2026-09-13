<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TransferForm from '@/Pages/Inventory/Transfers/TransferForm.vue';

interface WarehouseOption {
    value: number;
    label: string;
}

interface ProductOption {
    value: number;
    label: string;
    unit?: string | null;
}

interface Line {
    product_id: number | '';
    quantity: number;
}

defineProps<{
    transfer: {
        id: number;
        transfer_date: string;
        from_warehouse_id: number | '';
        to_warehouse_id: number | '';
        reference: string | null;
        memo: string | null;
    };
    lines: Line[];
    warehouses: WarehouseOption[] | null;
    products: ProductOption[] | null;
    systemQty: Record<string, string>;
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                title="Edit Stock Transfer Draft"
                description="Availability is checked against live stock when this draft is posted."
            />
            <TransferForm
                action="edit"
                :submit-url="route('stock-transfers.update', transfer.id)"
                :warehouses="warehouses"
                :products="products"
                :system-qty="systemQty"
                :today="today"
                :initial="{
                    transfer_date: transfer.transfer_date,
                    from_warehouse_id: transfer.from_warehouse_id,
                    to_warehouse_id: transfer.to_warehouse_id,
                    reference: transfer.reference ?? '',
                    memo: transfer.memo ?? '',
                    lines: lines.map((l) => ({ product_id: l.product_id, quantity: String(l.quantity) })),
                }"
            />
        </div>
    </AuthenticatedLayout>
</template>