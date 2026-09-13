<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RoleForm from './RoleForm.vue';

interface Permission { id: number; name: string; slug: string; action: string; }
interface PermissionGroup { module: string; permissions: Permission[]; }

defineProps<{
    permissionGroups: PermissionGroup[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="w-full">
            <PageHeader title="Create Role" description="Define a new role and its module permissions." />
            <RoleForm :permission-groups="permissionGroups" />
        </div>
    </AuthenticatedLayout>
</template>