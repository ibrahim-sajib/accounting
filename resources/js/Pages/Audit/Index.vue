<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router } from '@inertiajs/vue3';
import { formatDate } from '@/utils/formatDate';
import { ref } from 'vue';

interface DiffRow {
    field: string;
    old: string;
    new: string;
}

interface AuditRow {
    id: number;
    module: string;
    action: string;
    user: string | null;
    record_type: string | null;
    record_id: number | null;
    ip_address: string | null;
    created_at: string;
    diff: DiffRow[];
    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;
}

defineProps<{
    logs: {
        data: AuditRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { module?: string; action?: string; search?: string; from?: string; to?: string };
    modules: string[];
    actions: string[];
}>();

const selected = ref<AuditRow | null>(null);

const go = (params: Record<string, string>) => {
    router.get(route('audit.index'), params, { preserveState: true });
};

const setFilter = (key: string) => (e: Event) => {
    const value = (e.target as HTMLInputElement | HTMLSelectElement).value;
    const next = { module: '', action: '', search: '', from: '', to: '' };
    go({ ...next, ...(value ? { [key]: value } : {}) });
};

const openModal = (row: AuditRow) => {
    selected.value = row;
};

const json = (row: AuditRow) => {
    const oldVal = row.old_values ?? {};
    const newVal = row.new_values ?? {};
    return {
        old: JSON.stringify(oldVal, null, 2),
        new: JSON.stringify(newVal, null, 2),
    };
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Audit Log"
                description="Append-only record of every action taken across the system — searchable, filterable, and tamper-evident."
            />

            <div class="mt-6 flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Module</label>
                    <select
                        class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters?.module ?? ''"
                        @change="setFilter('module')"
                    >
                        <option value="">All modules</option>
                        <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Action</label>
                    <select
                        class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters?.action ?? ''"
                        @change="setFilter('action')"
                    >
                        <option value="">All actions</option>
                        <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
                    <input
                        type="date"
                        class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters?.from ?? ''"
                        @change="setFilter('from')"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">To</label>
                    <input
                        type="date"
                        class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters?.to ?? ''"
                        @change="setFilter('to')"
                    />
                </div>
                <div class="flex-1">
                    <SearchInput
                        :model-value="filters?.search ?? ''"
                        placeholder="Search user, record type or IP…"
                    />
                </div>
            </div>

            <div class="mt-4 overflow-hidden bg-white shadow-sm dark:bg-gray-900 sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">When</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">User</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Module</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Action</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Record</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">IP</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Changes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="row in logs.data"
                            :key="row.id"
                            class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            @click="openModal(row)"
                        >
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ formatDate(row.created_at) }}</td>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.user ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.module }}</td>
                            <td class="px-4 py-2">
                                <span
                                    class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                >{{ row.action }}</span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">
                                {{ row.record_type ?? '—' }}<span v-if="row.record_id"> · #{{ row.record_id }}</span>
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-xs text-gray-500 dark:text-gray-400">{{ row.ip_address ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <span
                                    v-if="row.diff.length"
                                    class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                                >{{ row.diff.length }} field{{ row.diff.length === 1 ? '' : 's' }}</span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No audit activity matches these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="logs.links" />
            </div>

            <Modal :show="selected !== null" @close="selected = null" max-width="2xl">
                <template v-if="selected">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ selected.module }} · {{ selected.action }}
                        </h2>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(selected.created_at) }}</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ selected.user ?? 'System' }}
                        <template v-if="selected.record_type"> — {{ selected.record_type }} #{{ selected.record_id }}</template>
                        <template v-if="selected.ip_address"> — {{ selected.ip_address }}</template>
                    </p>

                    <div v-if="selected.diff.length" class="mt-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Changed fields</h3>
                        <table class="mt-2 w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                                    <th class="py-1 pr-2 font-medium">Field</th>
                                    <th class="py-1 pr-2 font-medium">Before</th>
                                    <th class="py-1 font-medium">After</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="d in selected.diff" :key="d.field">
                                    <td class="py-1.5 pr-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ d.field }}</td>
                                    <td class="py-1.5 pr-2 text-xs text-gray-500 dark:text-gray-400 line-through">{{ d.old }}</td>
                                    <td class="py-1.5 text-xs text-gray-700 dark:text-gray-200">{{ d.new }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Before</h3>
                            <pre class="mt-2 max-h-64 overflow-auto rounded-md bg-gray-50 p-3 text-[11px] text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ json(selected).old }}</pre>
                        </div>
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">After</h3>
                            <pre class="mt-2 max-h-64 overflow-auto rounded-md bg-gray-50 p-3 text-[11px] text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ json(selected).new }}</pre>
                        </div>
                    </div>
                </template>
                <div class="mt-5 flex justify-end">
                    <SecondaryButton @click="selected = null">Close</SecondaryButton>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>