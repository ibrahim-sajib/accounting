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

interface ExpenseRow {
    id: number;
    expense_no: string | null;
    expense_date: string;
    payee: string;
    amount: number | string;
    tax_amount: number | string;
    payment_method: string;
    status: string;
    is_recurring: boolean;
    category: { id: number; name: string } | null;
}

interface CategoryOption {
    id: number;
    name: string;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('expense.create') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    expenses: {
        data: ExpenseRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; status?: string; category_id?: string };
    categories: CategoryOption[];
}>();

const totalOf = (expense: ExpenseRow) => Number(expense.amount) + Number(expense.tax_amount);
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Expenses" :description="`${expenses.total ?? 0} expense(s)`">
                <template #actions>
                    <Link
                        :href="route('expense-categories.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <AppIcon name="settings" class="h-4 w-4" />
                        Categories
                    </Link>
                    <Link
                        v-if="canCreate"
                        :href="route('expenses.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Expense
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput :model-value="filters?.search" placeholder="Search by payee, reference or no..." />

                <select
                    :value="filters?.status ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-40"
                    @change="(e: Event) => router.get(route('expenses.index'), { status: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                    <option value="recurring">Recurring</option>
                </select>

                <select
                    :value="filters?.category_id ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-48"
                    @change="(e: Event) => router.get(route('expenses.index'), { category_id: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
            </div>

            <div v-if="expenses.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="expense" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No expenses found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Expense</th>
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold">Payee</th>
                            <th class="px-4 py-3 font-semibold">Dated</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                            <th class="px-4 py-3 font-semibold">Method</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="expense in expenses.data" :key="expense.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('expenses.show', expense.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ expense.expense_no ?? 'Draft' }}
                                </Link>
                                <div class="text-xs text-gray-400">#{{ expense.id }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ expense.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ expense.payee }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(expense.expense_date) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(totalOf(expense)) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs capitalize text-gray-500 dark:text-gray-400">
                                    {{ expense.payment_method }}
                                    <span v-if="expense.is_recurring" class="text-indigo-500 dark:text-indigo-400"> · recurring</span>
                                </span>
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="expense.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('expenses.show', expense.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="expenses.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>