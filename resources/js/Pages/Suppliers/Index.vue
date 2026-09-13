<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Supplier {
    id: number;
    code: string;
    name: string;
    email: string | null;
    phone: string | null;
    tax_no: string | null;
    opening_balance: string;
    is_active: boolean;
    ap_account: { id: number; code: string; name: string } | null;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('supplier.create') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('supplier.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    suppliers: {
        data: Supplier[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string };
}>();

const remove = (supplier: Supplier) => {
    if (confirm(`Delete supplier ${supplier.name}?`)) {
        router.delete(route('suppliers.destroy', supplier.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Suppliers" :description="`${suppliers.total ?? 0} supplier(s)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('suppliers.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Supplier
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4">
                <SearchInput :model-value="filters?.search" placeholder="Search suppliers..." />
            </div>

            <div v-if="suppliers.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="supplier" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No suppliers found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Code</th>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Contact</th>
                            <th class="px-4 py-3 font-semibold">AP Account</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="supplier in suppliers.data" :key="supplier.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ supplier.code }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ supplier.name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                <div>{{ supplier.email ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ supplier.phone ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ supplier.ap_account?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs capitalize" :class="supplier.is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                    {{ supplier.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('suppliers.edit', supplier.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        Manage
                                    </Link>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="remove(supplier)">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="suppliers.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>