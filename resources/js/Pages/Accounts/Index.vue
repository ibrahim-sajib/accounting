<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Account {
    id: number;
    code: string;
    name: string;
    name_bn: string | null;
    type: string;
    parent_id: number | null;
    level: number;
    normal_balance: string;
    is_postable: boolean;
    is_active: boolean;
    is_system: boolean;
}

interface AccountOption {
    id: number;
    code: string;
    name: string;
    level: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('account.create') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('account.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('account.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    accounts: Account[];
    parentOptions: AccountOption[];
    filters: { search?: string };
}>();

const search = ref(props.filters.search ?? '');

const typeFilter = ref('');
const typeOptions = ['asset', 'liability', 'equity', 'income', 'expense'];

const typeBadge = (type: string) => {
    const map: Record<string, string> = {
        asset: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
        liability: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
        equity: 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
        income: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
        expense: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
    };
    return map[type] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
};

const filteredAccounts = computed(() => {
    let list = props.accounts;
    if (typeFilter.value) {
        list = list.filter(a => a.type === typeFilter.value);
    }
    if (search.value) {
        const q = search.value.toLowerCase();
        list = list.filter(a => a.code.toLowerCase().includes(q) || a.name.toLowerCase().includes(q));
    }
    return list;
});

const indentClass = (level: number) => `padding-left: ${level * 24}px`;
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Chart of Accounts" description="Hierarchical ledger accounts by type.">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('accounts.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Account
                    </Link>
                </template>
            </PageHeader>

            <!-- Filters -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="relative max-w-xs flex-1">
                    <AppIcon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Filter by code or name…"
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                    />
                </div>
                <select
                    v-model="typeFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">All Types</option>
                    <option v-for="t in typeOptions" :key="t" :value="t">{{ t.charAt(0).toUpperCase() + t.slice(1) }}</option>
                </select>
            </div>

            <!-- Table -->
            <div v-if="filteredAccounts.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="account" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No accounts found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Code</th>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Normal Balance</th>
                            <th class="px-4 py-3 font-semibold">Postable</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="account in filteredAccounts" :key="account.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="whitespace-nowrap px-4 py-2.5">
                                <span :style="indentClass(account.level)" class="font-mono text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ account.code }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ account.name }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="typeBadge(account.type)">
                                    {{ account.type.toUpperCase() }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">
                                {{ account.normal_balance === 'debit' ? 'Debit' : 'Credit' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <AppIcon v-if="account.is_postable" name="check" class="mx-auto h-4 w-4 text-green-600 dark:text-green-400" />
                                <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <StatusBadge :status="account.is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <div v-if="!account.is_system" class="flex justify-end gap-2">
                                    <Link
                                        v-if="canUpdate"
                                        :href="route('accounts.edit', account.id)"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                    >
                                        <AppIcon name="pencil" class="h-3.5 w-3.5" />
                                        Edit
                                    </Link>
                                    <button
                                        v-if="canDelete"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                                        @click="router.delete(route('accounts.destroy', account.id), { preserveScroll: true })"
                                    >
                                        <AppIcon name="trash" class="h-3.5 w-3.5" />
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>