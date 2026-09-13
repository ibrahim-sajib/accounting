<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BranchForm from './BranchForm.vue';

defineProps<{
    company: { id: number; name: string } | null;
    managers: { value: number; label: string }[];
    statusOptions: { value: string; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Create Branch" description="Add a new branch to the current company." />
            <BranchForm :company="company" :managers="managers" :status-options="statusOptions" />
        </div>
    </AuthenticatedLayout>
</template>