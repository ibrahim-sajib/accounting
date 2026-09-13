<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('role.create') || page.props.auth.user.is_super_admin);

defineProps<{
    roles: {
        data: Role[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string };
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Roles & Permissions" description="Define role-based access control across modules.">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('roles.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Role
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4">
                <SearchInput :model-value="filters?.search" placeholder="Search roles..." />
            </div>

            <div v-if="roles.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="shield" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No roles found.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="role in roles.data"
                    :key="role.id"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
                                <AppIcon name="shield" class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                                    {{ role.name }}
                                    <span
                                        v-if="role.is_system"
                                        class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                                    >
                                        SYSTEM
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500">{{ role.users_count }} user(s)</div>
                            </div>
                        </div>
                    </div>
                    <p v-if="role.description" class="mt-3 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ role.description }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Link
                            v-if="!role.is_system || page.props.auth.user.is_super_admin"
                            :href="route('roles.edit', role.id)"
                            class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Configure Permissions
                        </Link>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <Pagination :links="roles.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>