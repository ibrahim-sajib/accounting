<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface AssetRow {
    id: number;
    asset_code: string;
    name: string;
    acquisition_date: string;
    acquisition_cost: number | string;
    useful_life_months: number;
    method: string;
    location: string | null;
    status: string;
    category: { id: number; name: string } | null;
}

interface CategoryOption {
    id: number;
    name: string;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('fixed_asset.create') || page.props.auth.user.is_super_admin);
const canDepreciate = computed(() => page.props.auth.permissions.includes('fixed_asset.depreciate') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    assets: {
        data: AssetRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; status?: string; category_id?: string };
    categories: CategoryOption[];
    periods: { value: number; label: string }[];
}>();

const depForm = useForm({ period_id: '' as number | '' });

const runDepreciation = () => {
    depForm.post(route('fixed-assets.depreciate'), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Fixed Assets" :description="`${assets.total ?? 0} asset(s)`">
                <template #actions>
                    <Link
                        :href="route('asset-categories.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <AppIcon name="settings" class="h-4 w-4" />
                        Categories
                    </Link>
                    <Link
                        v-if="canCreate"
                        :href="route('fixed-assets.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        Register Asset
                    </Link>
                </template>
            </PageHeader>

            <form
                v-if="canDepreciate && periods.length > 0"
                class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center dark:border-gray-800 dark:bg-gray-900"
                @submit.prevent="runDepreciation"
            >
                <AppIcon name="calendar" class="h-5 w-5 text-gray-400" />
                <div class="flex-1">
                    <select
                        id="dep_period"
                        v-model="depForm.period_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-56"
                        required
                    >
                        <option value="">Select period…</option>
                        <option v-for="p in periods" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Posts one DEP journal covering every active asset not yet depreciated for the period.</p>
                </div>
                <PrimaryButton :disabled="depForm.processing || !depForm.period_id">
                    Run Depreciation
                </PrimaryButton>
            </form>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput :model-value="filters?.search" placeholder="Search by name, code or location..." />

                <select
                    :value="filters?.status ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-40"
                    @change="(e: Event) => router.get(route('fixed-assets.index'), { status: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="disposed">Disposed</option>
                </select>

                <select
                    :value="filters?.category_id ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-48"
                    @change="(e: Event) => router.get(route('fixed-assets.index'), { category_id: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All categories</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <div v-if="assets.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="asset" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No fixed assets found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Asset</th>
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold">Acquired</th>
                            <th class="px-4 py-3 text-right font-semibold">Cost</th>
                            <th class="px-4 py-3 font-semibold">Life</th>
                            <th class="px-4 py-3 font-semibold">Method</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="asset in assets.data" :key="asset.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('fixed-assets.show', asset.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ asset.name }}
                                </Link>
                                <div class="text-xs text-gray-400">{{ asset.asset_code }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ asset.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(asset.acquisition_date) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(asset.acquisition_cost) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ asset.useful_life_months }} mo</td>
                            <td class="px-4 py-3 text-xs capitalize text-gray-500 dark:text-gray-400">{{ asset.method.replace('_', ' ') }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="asset.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('fixed-assets.show', asset.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="assets.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>