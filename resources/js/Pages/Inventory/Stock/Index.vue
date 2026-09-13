<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router } from '@inertiajs/vue3';
import { formatMoney } from '@/utils/formatMoney';

interface StockRow {
    id: number;
    sku: string | null;
    name: string;
    unit?: string | null;
    track_inventory: boolean;
    threshold: number | null;
    qty: number;
    cost: number;
    value: number;
    is_low_stock: boolean;
}

interface WarehouseOption {
    value: number;
    label: string;
}

const props = defineProps<{
    records: {
        data: StockRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; warehouse_id?: number };
    warehouses: WarehouseOption[] | null;
    summary: { total_value: number; product_count: number; low_stock_count: number };
}>();

const onWarehouseChange = (e: Event) => {
    const value = (e.target as HTMLSelectElement).value;
    router.get(route('stock.index'), {
        warehouse_id: value || undefined,
        search: props.filters?.search || undefined,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Stock"
                :description="`Current on-hand stock · ${records.total ?? 0} product(s)`"
            />

            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Stock value</div>
                    <div class="mt-1 font-mono text-xl font-bold text-gray-900 dark:text-white">{{ formatMoney(summary.total_value) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Tracked products</div>
                    <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ summary.product_count }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Low stock</div>
                    <div class="mt-1 text-xl font-bold" :class="summary.low_stock_count > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                        {{ summary.low_stock_count }}
                    </div>
                </div>
            </div>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="w-full sm:max-w-xs">
                    <SearchInput
                        :model-value="filters?.search"
                        placeholder="Search product or SKU..."
                        query-param="search"
                    />
                </div>
                <select
                    :value="filters?.warehouse_id ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-64"
                    @change="onWarehouseChange"
                >
                    <option value="">All warehouses</option>
                    <option v-for="w in warehouses ?? []" :key="w.value" :value="w.value">{{ w.label }}</option>
                </select>
            </div>

            <div v-if="records.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="stock" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No products found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Product</th>
                            <th class="px-4 py-3 font-semibold">Available qty</th>
                            <th class="px-4 py-3 text-right font-semibold">Avg cost</th>
                            <th class="px-4 py-3 text-right font-semibold">Total value</th>
                            <th class="px-4 py-3 text-right font-semibold">Low stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="row in records.data"
                            :key="row.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            :class="row.is_low_stock ? 'bg-red-50/60 dark:bg-red-950/30' : ''"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('products.edit', row.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ row.name }}
                                </Link>
                                <div class="text-xs text-gray-400">
                                    {{ row.sku ?? 'no SKU' }}<template v-if="row.unit"> · {{ row.unit }}</template>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono font-semibold" :class="row.is_low_stock ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                                    {{ row.qty }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ formatMoney(row.cost) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.value) }}</td>
                            <td class="px-4 py-3 text-right">
                                <span v-if="row.is_low_stock" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/50 dark:text-red-300">
                                    <AppIcon name="alert" class="h-3 w-3" />
                                    ≤ {{ row.threshold }}
                                </span>
                                <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="records.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>