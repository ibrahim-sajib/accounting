<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Branch {
    id: number;
    code: string;
    name: string;
    city: string | null;
    phone: string | null;
    status: string;
    manager: { id: number; name: string } | null;
    company_id: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('branch.create') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    branches: {
        data: Branch[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string };
    companies: { id: number; name: string }[] | null;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Branches" :description="`${branches.total ?? 0} branch(es)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('branches.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Branch
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4">
                <SearchInput :model-value="filters?.search" placeholder="Search branches..." />
            </div>

            <div v-if="branches.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="branch" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No branches found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Code</th>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">City</th>
                            <th class="px-4 py-3 font-semibold">Manager</th>
                            <th class="px-4 py-3 font-semibold">Phone</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="branch in branches.data" :key="branch.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ branch.code }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ branch.name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ branch.city ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ branch.manager?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ branch.phone ?? '—' }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="branch.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('branches.edit', branch.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    Manage
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="branches.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>