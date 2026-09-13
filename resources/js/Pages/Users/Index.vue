<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    is_super_admin: boolean;
    company: { id: number; name: string } | null;
    roles: { id: number; name: string; slug: string }[];
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('user.create') || page.props.auth.user.is_super_admin);

defineProps<{
    users: {
        data: User[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string };
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Users" description="Manage team members, roles and company access.">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('users.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New User
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4">
                <SearchInput :model-value="filters?.search" placeholder="Search users..." />
            </div>

            <div v-if="users.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="users" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No users found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">User</th>
                            <th class="px-4 py-3 font-semibold">Company</th>
                            <th class="px-4 py-3 font-semibold">Roles</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                                            {{ user.name }}
                                            <span v-if="user.is_super_admin" class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                                SUPER ADMIN
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500">{{ user.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ user.company?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300"
                                    >
                                        {{ role.name }}
                                    </span>
                                    <span v-if="user.roles.length === 0" class="text-xs text-gray-400">No roles</span>
                                </div>
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="user.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('users.edit', user.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    Manage
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="users.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>