<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WarehouseForm from './WarehouseForm.vue';

defineProps<{
    warehouse: {
        id: number;
        code: string;
        name: string;
        address: string | null;
        branch_id: number | null;
        manager_user_id: number | null;
        is_active: boolean;
    };
    branches: { value: number; label: string }[];
    managers: { value: number; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Edit Warehouse" description="Update warehouse details and assignment." />
            <WarehouseForm :warehouse="warehouse" :branches="branches" :managers="managers" />
        </div>
    </AuthenticatedLayout>
</template>