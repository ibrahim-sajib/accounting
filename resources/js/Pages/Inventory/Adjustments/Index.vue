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
import { formatMoney } from '@/utils/formatMoney';

interface Adjustment {
    id: number;
    adjustment_no: string | null;
    adjustment_date: string;
    warehouse: string | null;
    reason: string | null;
    reason_label: string | null;
    status: string;
    total_value: number;
    line_count: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('inventory.create') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    adjustments: {
        data: Adjustment[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; status?: string };
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Stock Adjustments" :description="`${adjustments.total ?? 0} adjustment(s)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('stock-adjustments.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Adjustment
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput :model-value="filters?.search" placeholder="Search adjustment no or reason..." />
                <select
                    :value="filters?.status ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-48"
                    @change="(e: Event) => router.get(route('stock-adjustments.index'), { status: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                </select>
            </div>

            <div v-if="adjustments.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="adjust" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No adjustments found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Adjustment</th>
                            <th class="px-4 py-3 font-semibold">Reason</th>
                            <th class="px-4 py-3 font-semibold">Warehouse</th>
                            <th class="px-4 py-3 text-right font-semibold">Lines</th>
                            <th class="px-4 py-3 text-right font-semibold">Value</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="adjustment in adjustments.data" :key="adjustment.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('stock-adjustments.show', adjustment.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ adjustment.adjustment_no ?? 'Draft' }}
                                </Link>
                                <div class="text-xs text-gray-400">{{ formatDate(adjustment.adjustment_date) }} · #{{ adjustment.id }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ adjustment.reason_label ?? adjustment.reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ adjustment.warehouse ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ adjustment.line_count }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(adjustment.total_value) }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="adjustment.status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('stock-adjustments.show', adjustment.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="adjustments.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>