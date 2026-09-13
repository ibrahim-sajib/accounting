<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';
import { formatDate } from '@/utils/formatDate';

interface JournalRow {
    id: number;
    journal_no: string | null;
    journal_date: string;
    reference: string | null;
    description: string | null;
    source_type: string;
    status: string;
    lines_count: number;
    total_debit: number;
    total_credit: number;
    posted_at: string | null;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('journal.create') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    journals: {
        data: JournalRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; status?: string };
    statusOptions: { value: string; label: string }[];
}>();

const onStatus = (e: Event) => {
    const value = (e.target as HTMLSelectElement).value;
    router.get(route('journals.index'), value && value !== 'all' ? { status: value } : {}, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Journals" :description="`${journals.total ?? 0} journal entrie(s)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('journals.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Journal
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row">
                <div class="flex-1">
                    <SearchInput :model-value="filters?.search" placeholder="Search by no, reference or memo..." />
                </div>
                <select
                    :value="filters?.status ?? 'all'"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    @change="onStatus"
                >
                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div v-if="journals.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="journal" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No journal entries found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[1080px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Journal No</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Memo</th>
                            <th class="px-4 py-3 text-right font-semibold">Lines</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="journal in journals.data" :key="journal.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('journals.show', journal.id)" class="font-mono text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ journal.journal_no ?? 'DRAFT' }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(journal.journal_date) }}</td>
                            <td class="max-w-[18rem] truncate px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ journal.reference ?? journal.description ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ journal.lines_count }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-800 dark:text-gray-100">{{ formatMoney(journal.total_debit) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="journal.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('journals.show', journal.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="journals.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>