<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RoleForm from './RoleForm.vue';

interface Permission { id: number; name: string; slug: string; action: string; }
interface PermissionGroup { module: string; permissions: Permission[]; }

defineProps<{
    role: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        is_system: boolean;
        company_id: number | null;
    };
    selectedPermissions: number[];
    permissionGroups: PermissionGroup[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="w-full">
            <PageHeader title="Edit Role" description="Update role details and configure permissions." />
            <RoleForm
                :role="role"
                :selected-permissions="selectedPermissions"
                :permission-groups="permissionGroups"
            />
        </div>
    </AuthenticatedLayout>
</template>