<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UserForm from './UserForm.vue';

interface Option { value: number; label: string; }

defineProps<{
    companies: { id: number; name: string }[] | null;
    roles: Option[];
    statusOptions: { value: string; label: string }[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Create User" description="Add a new team member with roles and company access." />
            <UserForm :companies="companies" :roles="roles" :status-options="statusOptions" />
        </div>
    </AuthenticatedLayout>
</template>