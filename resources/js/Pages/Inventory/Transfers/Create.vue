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

defineProps<{
    warehouses: WarehouseOption[] | null;
    products: ProductOption[] | null;
    systemQty: Record<string, string>;
    today?: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="New Stock Transfer"
                description="Moving stock between warehouses has no accounting impact — only the warehouse balances change."
            />
            <TransferForm
                action="create"
                :submit-url="route('stock-transfers.store')"
                :warehouses="warehouses"
                :products="products"
                :system-qty="systemQty"
                :today="today"
            />
        </div>
    </AuthenticatedLayout>
</template>