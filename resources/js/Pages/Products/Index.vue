<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Product {
    id: number;
    sku: string;
    name: string;
    type: string;
    category: { id: number; name: string } | null;
    unit: { id: number; name: string; symbol: string | null } | null;
    sales_price: string;
    track_inventory: boolean;
    is_active: boolean;
}

interface Category {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
    symbol: string | null;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('product.create') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('product.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    products: {
        data: Product[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string };
    categories: Category[];
    units: Unit[];
}>();

const showCategoryModal = ref(false);
const showUnitModal = ref(false);

const categoryForm = useForm({ id: null as number | null, name: '', parent_id: '', is_active: true });
const unitForm = useForm({ id: null as number | null, name: '', symbol: '', is_active: true });

const openCategoryModal = (category?: Category) => {
    categoryForm.clearErrors();
    categoryForm.id = category?.id ?? null;
    categoryForm.name = category?.name ?? '';
    showCategoryModal.value = true;
};

const submitCategory = () => {
    if (categoryForm.id) {
        categoryForm.put(route('product-categories.update', categoryForm.id), {
            onSuccess: () => { showCategoryModal.value = false; categoryForm.reset(); },
        });
    } else {
        categoryForm.post(route('product-categories.store'), {
            onSuccess: () => { showCategoryModal.value = false; categoryForm.reset(); },
        });
    }
};

const removeCategory = (category: Category) => {
    if (confirm(`Delete category ${category.name}?`)) {
        router.delete(route('product-categories.destroy', category.id), { preserveScroll: true });
    }
};

const openUnitModal = (unit?: Unit) => {
    unitForm.clearErrors();
    unitForm.id = unit?.id ?? null;
    unitForm.name = unit?.name ?? '';
    unitForm.symbol = unit?.symbol ?? '';
    showUnitModal.value = true;
};

const submitUnit = () => {
    if (unitForm.id) {
        unitForm.put(route('units.update', unitForm.id), {
            onSuccess: () => { showUnitModal.value = false; unitForm.reset(); },
        });
    } else {
        unitForm.post(route('units.store'), {
            onSuccess: () => { showUnitModal.value = false; unitForm.reset(); },
        });
    }
};

const removeUnit = (unit: Unit) => {
    if (confirm(`Delete unit ${unit.name}?`)) {
        router.delete(route('units.destroy', unit.id), { preserveScroll: true });
    }
};

const remove = (product: Product) => {
    if (confirm(`Delete product ${product.name}?`)) {
        router.delete(route('products.destroy', product.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Products & Services" :description="`${products.total ?? 0} item(s)`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('products.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Product
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4">
                <SearchInput :model-value="filters?.search" placeholder="Search products..." />
            </div>

            <div v-if="products.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="product" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No products found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">SKU</th>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold">Unit</th>
                            <th class="px-4 py-3 font-semibold">Sales Price</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ product.sku }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ product.name }}</td>
                            <td class="px-4 py-3 text-xs capitalize text-gray-600 dark:text-gray-300">{{ product.type }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ product.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ product.unit?.symbol ?? product.unit?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ Number(product.sales_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs capitalize" :class="product.is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                    {{ product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('products.edit', product.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        Manage
                                    </Link>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="remove(product)">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="products.links" />
            </div>

            <!-- Reference data: categories & units -->
            <div class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Categories</h3>
                        <button v-if="canCreate" type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openCategoryModal()">
                            + Add
                        </button>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="category in categories" :key="category.id" class="flex items-center justify-between py-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-200">{{ category.name }}</span>
                            <span class="flex gap-3">
                                <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openCategoryModal(category)">Edit</button>
                                <button v-if="canDelete" type="button" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="removeCategory(category)">Delete</button>
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Units of Measure</h3>
                        <button v-if="canCreate" type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openUnitModal()">
                            + Add
                        </button>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="unit in units" :key="unit.id" class="flex items-center justify-between py-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-200">
                                {{ unit.name }}
                                <span v-if="unit.symbol" class="ml-1 text-xs text-gray-400">({{ unit.symbol }})</span>
                            </span>
                            <span class="flex gap-3">
                                <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openUnitModal(unit)">Edit</button>
                                <button v-if="canDelete" type="button" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="removeUnit(unit)">Delete</button>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Category modal -->
            <Modal :show="showCategoryModal" @close="showCategoryModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ categoryForm.id ? 'Edit Category' : 'New Category' }}</h2>
                    <form @submit.prevent="submitCategory" class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="category_name" value="Category Name" required />
                            <TextInput id="category_name" v-model="categoryForm.name" type="text" class="mt-1 block w-full" required />
                            <InputError :message="categoryForm.errors.name" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showCategoryModal = false">
                                Cancel
                            </button>
                            <PrimaryButton :disabled="categoryForm.processing">Save Category</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Unit modal -->
            <Modal :show="showUnitModal" @close="showUnitModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ unitForm.id ? 'Edit Unit' : 'New Unit' }}</h2>
                    <form @submit.prevent="submitUnit" class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="unit_name" value="Unit Name" required />
                            <TextInput id="unit_name" v-model="unitForm.name" type="text" class="mt-1 block w-full" placeholder="Piece" required />
                            <InputError :message="unitForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="unit_symbol" value="Symbol" />
                            <TextInput id="unit_symbol" v-model="unitForm.symbol" type="text" class="mt-1 block w-full" placeholder="pc" maxlength="20" />
                            <InputError :message="unitForm.errors.symbol" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showUnitModal = false">
                                Cancel
                            </button>
                            <PrimaryButton :disabled="unitForm.processing">Save Unit</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>