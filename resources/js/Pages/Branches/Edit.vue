<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BranchForm from './BranchForm.vue';

defineProps<{
    branch: {
        id: number;
        code: string;
        name: string;
        address: string | null;
        city: string | null;
        state: string | null;
        zip_code: string | null;
        country_code: string | null;
        email: string | null;
        phone: string | null;
        manager_user_id: number | null;
        status: string;
    };
    company: { id: number; name: string } | null;
    managers: { value: number; label: string }[];
    statusOptions: { value: string; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Edit Branch" description="Update branch details." />
            <BranchForm :branch="branch" :company="company" :managers="managers" :status-options="statusOptions" />
        </div>
    </AuthenticatedLayout>
</template>