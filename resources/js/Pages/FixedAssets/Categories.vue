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
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AccountOption {
    id: number;
    code: string;
    name: string;
}

interface CategoryRow {
    id: number;
    name: string;
    is_active: boolean;
    default_method: string;
    default_useful_life_months: number;
    asset_account: { id: number; code: string; name: string } | null;
    depreciation_expense_account: { id: number; code: string; name: string } | null;
    accumulated_depreciation_account: { id: number; code: string; name: string } | null;
    assets_count?: number;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('fixed_asset.create') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('fixed_asset.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('fixed_asset.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    categories: {
        data: CategoryRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    postableAccounts: AccountOption[];
}>();

const showCreate = ref(false);
const editingCategory = ref<CategoryRow | null>(null);

const form = useForm({
    name: '',
    asset_account_id: '' as number | '',
    depreciation_expense_account_id: '' as number | '',
    accumulated_depreciation_account_id: '' as number | '',
    default_method: 'straight_line',
    default_useful_life_months: '36',
    is_active: true,
});

const openCreate = () => {
    form.clearErrors();
    form.reset();
    showCreate.value = true;
};

const openEdit = (category: CategoryRow) => {
    editingCategory.value = category;
    form.clearErrors();
    form.name = category.name;
    form.asset_account_id = category.asset_account?.id ?? '';
    form.depreciation_expense_account_id = category.depreciation_expense_account?.id ?? '';
    form.accumulated_depreciation_account_id = category.accumulated_depreciation_account?.id ?? '';
    form.default_method = category.default_method;
    form.default_useful_life_months = String(category.default_useful_life_months);
    form.is_active = category.is_active;
};

const submitCreate = () => {
    form.post(route('asset-categories.store'), {
        onSuccess: () => {
            form.reset();
            showCreate.value = false;
        },
    });
};

const submitEdit = () => {
    if (!editingCategory.value) return;
    form.put(route('asset-categories.update', editingCategory.value.id), {
        onSuccess: () => {
            form.reset();
            editingCategory.value = null;
        },
    });
};

const destroyCategory = (category: CategoryRow) => {
    if (confirm(`Delete "${category.name}"? If it is in use, it will be deactivated instead.`)) {
        router.delete(route('asset-categories.destroy', category.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Asset Categories" :description="`${categories.total ?? 0} category(ies)`">
                <template #actions>
                    <Link
                        :href="route('fixed-assets.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Back to Fixed Assets
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
                <AppIcon name="asset" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No asset categories found.</p>
            </div>

            <div v-else class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Asset Account</th>
                            <th class="px-4 py-3 font-semibold">Accum. Depreciation</th>
                            <th class="px-4 py-3 font-semibold">Dep. Expense</th>
                            <th class="px-4 py-3 font-semibold">Defaults</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="c in categories.data" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ c.name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ c.asset_account ? `${c.asset_account.code} · ${c.asset_account.name}` : '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ c.accumulated_depreciation_account ? `${c.accumulated_depreciation_account.code} · ${c.accumulated_depreciation_account.name}` : '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ c.depreciation_expense_account ? `${c.depreciation_expense_account.code} · ${c.depreciation_expense_account.name}` : '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ c.default_method.replace('_', ' ') }} · {{ c.default_useful_life_months }} mo</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="c.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                                    {{ c.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="canUpdate || canDelete" class="flex justify-end gap-2">
                                    <button v-if="canUpdate" type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openEdit(c)">Edit</button>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="destroyCategory(c)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="categories.links" />
            </div>

            <Modal :show="showCreate || !!editingCategory" max-width="lg" @close="editingCategory = null; showCreate = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ editingCategory ? 'Edit Asset Category' : 'New Asset Category' }}
                    </h2>

                    <form class="mt-4 space-y-4" @submit.prevent="editingCategory ? submitEdit() : submitCreate()">
                        <div>
                            <InputLabel for="cat_name" value="Name *" />
                            <TextInput id="cat_name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="cat_asset_account" value="Asset account *" />
                            <select id="cat_asset_account" v-model="form.asset_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                <option value="">Select account…</option>
                                <option v-for="a in postableAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.asset_account_id" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="cat_accum_account" value="Accumulated depreciation *" />
                                <select id="cat_accum_account" v-model="form.accumulated_depreciation_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                    <option value="">Select account…</option>
                                    <option v-for="a in postableAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.accumulated_depreciation_account_id" />
                            </div>
                            <div>
                                <InputLabel for="cat_dep_account" value="Depreciation expense *" />
                                <select id="cat_dep_account" v-model="form.depreciation_expense_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                    <option value="">Select account…</option>
                                    <option v-for="a in postableAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.depreciation_expense_account_id" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="cat_method" value="Default method" />
                                <select id="cat_method" v-model="form.default_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                                    <option value="straight_line">Straight Line</option>
                                    <option value="declining_balance">Declining Balance</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="cat_life" value="Default useful life (months) *" />
                                <TextInput id="cat_life" v-model="form.default_useful_life_months" type="number" min="1" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.default_useful_life_months" />
                            </div>
                        </div>

                        <label class="inline-flex cursor-pointer items-center">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-200">Active</span>
                        </label>

                        <div class="flex justify-end gap-3 pt-2">
                            <SecondaryButton @click="editingCategory = null; showCreate = false">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="form.processing">{{ editingCategory ? 'Save Changes' : 'Create Category' }}</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>