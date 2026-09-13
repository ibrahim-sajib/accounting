<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';

interface Transfer {
    id: number;
    transfer_no: string | null;
    transfer_date: string;
    from_warehouse: string | null;
    to_warehouse: string | null;
    reference: string | null;
    status: string;
    line_count: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('inventory.create') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    transfers: {
        data: Transfer[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; status?: string };
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Stock Transfers" :description="`${transfers.total ?? 0} transfer(s)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('stock-transfers.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Transfer
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput :model-value="filters?.search" placeholder="Search transfer no or reference..." />
                <select
                    :value="filters?.status ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-48"
                    @change="(e: Event) => router.get(route('stock-transfers.index'), { status: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                </select>
            </div>

            <div v-if="transfers.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="transfer" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No transfers found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Transfer</th>
                            <th class="px-4 py-3 font-semibold">From → To</th>
                            <th class="px-4 py-3 text-right font-semibold">Lines</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="transfer in transfers.data" :key="transfer.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('stock-transfers.show', transfer.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ transfer.transfer_no ?? 'Draft' }}
                                </Link>
                                <div class="text-xs text-gray-400">{{ formatDate(transfer.transfer_date) }} · #{{ transfer.id }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                <span class="font-medium">{{ transfer.from_warehouse ?? '—' }}</span>
                                <AppIcon name="chevronRight" class="mx-1 inline h-3 w-3 text-gray-400" />
                                <span class="font-medium">{{ transfer.to_warehouse ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ transfer.line_count }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="transfer.status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('stock-transfers.show', transfer.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="transfers.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>