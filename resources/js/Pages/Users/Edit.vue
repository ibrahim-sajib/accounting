<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UserForm from './UserForm.vue';

interface Option { value: number; label: string; }

defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        status: string;
        company_id: number | null;
    };
    companies: { id: number; name: string }[] | null;
    roles: Option[];
    statusOptions: { value: string; label: string }[];
    userAccess: { company_id: number; branch_id: number | null; is_default: boolean }[];
    userRoles: number[];
    branches?: Option[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Edit User" description="Update team member details and access." />
            <UserForm
                :user="user"
                :companies="companies"
                :roles="roles"
                :status-options="statusOptions"
                :user-access="userAccess"
                :user-roles="userRoles"
                :branches="branches"
            />
        </div>
    </AuthenticatedLayout>
</template>