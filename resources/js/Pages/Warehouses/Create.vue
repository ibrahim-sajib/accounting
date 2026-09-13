<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WarehouseForm from './WarehouseForm.vue';

defineProps<{
    branches: { value: number; label: string }[];
    managers: { value: number; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Create Warehouse" description="Add a new warehouse to the current company." />
            <WarehouseForm :branches="branches" :managers="managers" />
        </div>
    </AuthenticatedLayout>
</template>