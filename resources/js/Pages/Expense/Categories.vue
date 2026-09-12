<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AccountOption {
    id: number;
    code: string;
    name: string;
}

interface ExpenseCategoryRow {
    id: number;
    name: string;
    is_active: boolean;
    expense_account: { id: number; code: string; name: string } | null;
    expenses_count?: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('expense.create') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('expense.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('expense.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    categories: {
        data: ExpenseCategoryRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    postableAccounts: AccountOption[];
}>();

const showCreate = ref(false);
const editingCategory = ref<ExpenseCategoryRow | null>(null);

const form = useForm({
    name: '',
    expense_account_id: '' as number | '',
    is_active: true,
});

const openCreate = () => {
    form.clearErrors();
    form.reset();
    showCreate.value = true;
};

const openEdit = (category: ExpenseCategoryRow) => {
    editingCategory.value = category;
    form.clearErrors();
    form.name = category.name;
    form.expense_account_id = category.expense_account?.id ?? '';
    form.is_active = category.is_active;
};

const submitCreate = () => {
    form.post(route('expense-categories.store'), {
        onSuccess: () => {
            form.reset();
            showCreate.value = false;
        },
    });
};

const submitEdit = () => {
    if (!editingCategory.value) return;
    form.put(route('expense-categories.update', editingCategory.value.id), {
        onSuccess: () => {
            form.reset();
            editingCategory.value = null;
        },
    });
};

const destroyCategory = (category: ExpenseCategoryRow) => {
    if (confirm(`Delete "${category.name}"? If it is in use, it will be deactivated instead.`)) {
        router.delete(route('expense-categories.destroy', category.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Expense Categories" :description="`${categories.total ?? 0} category(ies)`">
                <template #actions>
                    <Link
                        :href="route('expenses.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Back to Expenses
                    </Link>
                    <button
                        v-if="canCreate"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="openCreate"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Category
                    </button>
                </template>
            </PageHeader>

            <SearchInput :model-value="' '" placeholder="Search categories..." />

            <div v-if="categories.data.length === 0" class="mt-4 rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="settings" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No expense categories found.</p>
            </div>

            <div v-else class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold">Default Expense Account</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="category in categories.data" :key="category.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ category.name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                <template v-if="category.expense_account">
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ category.expense_account.code }}</span>
                                    <span class="ml-1 text-gray-900 dark:text-white">{{ category.expense_account.name }}</span>
                                </template>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="category.is_active
                                        ? 'inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                        : 'inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
                                >
                                    {{ category.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        v-if="canUpdate"
                                        type="button"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                        @click="openEdit(category)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="canDelete"
                                        type="button"
                                        class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                                        @click="destroyCategory(category)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="categories.links" />
            </div>
        </div>

        <Modal :show="showCreate || !!editingCategory" max-width="lg" @close="showCreate = false; editingCategory = null">
            <form class="p-6" @submit.prevent="editingCategory ? submitEdit() : submitCreate()">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ editingCategory ? 'Edit Expense Category' : 'New Expense Category' }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Categories group expenses and set a default expense account.</p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="cat_name" value="Category name *" />
                        <TextInput id="cat_name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="e.g. Rent, Travel, Office Supplies..." required />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="cat_expense_account" value="Default expense account *" />
                        <select
                            id="cat_expense_account"
                            v-model="form.expense_account_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            required
                        >
                            <option value="">Select account…</option>
                            <option v-for="account in postableAccounts" :key="account.id" :value="account.id">
                                {{ account.code }} — {{ account.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.expense_account_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Active</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showCreate = false; editingCategory = null">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editingCategory ? 'Save Changes' : 'Create Category' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>