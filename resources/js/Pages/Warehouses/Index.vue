<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Warehouse {
    id: number;
    code: string;
    name: string;
    address: string | null;
    is_active: boolean;
    branch: { id: number; name: string } | null;
    manager: { id: number; name: string } | null;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('warehouse.create') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('warehouse.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    warehouses: {
        data: Warehouse[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string };
}>();

const remove = (warehouse: Warehouse) => {
    if (confirm(`Delete warehouse ${warehouse.name}?`)) {
        router.delete(route('warehouses.destroy', warehouse.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Warehouses" :description="`${warehouses.total ?? 0} warehouse(s)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('warehouses.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Warehouse
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4">
                <SearchInput :model-value="filters?.search" placeholder="Search warehouses..." />
            </div>

            <div v-if="warehouses.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="warehouse" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No warehouses found.</p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Code</th>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Address</th>
                            <th class="px-4 py-3 font-semibold">Branch</th>
                            <th class="px-4 py-3 font-semibold">Manager</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="warehouse in warehouses.data" :key="warehouse.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ warehouse.code }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ warehouse.name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ warehouse.address ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ warehouse.branch?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ warehouse.manager?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs capitalize" :class="warehouse.is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                    {{ warehouse.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('warehouses.edit', warehouse.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        Manage
                                    </Link>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="remove(warehouse)">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="warehouses.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>