<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    product?: {
        id: number;
        sku: string;
        name: string;
        type: string;
        category_id: number | null;
        unit_id: number | null;
        purchase_price: string | number;
        sales_price: string | number;
        tax_rate_id: number | null;
        inventory_account_id: number | null;
        sales_account_id: number | null;
        purchase_account_id: number | null;
        cogs_account_id: number | null;
        track_inventory: boolean;
        low_stock_threshold: number | null;
        is_active: boolean;
    };
    defaults: { inventory: number | null; sales: number | null; purchase: number | null; cogs: number | null };
    accountOptions: { value: number; label: string }[];
    taxRateOptions: { value: number; label: string }[];
    categoryOptions: { value: number; label: string }[];
    unitOptions: { value: number; label: string }[];
    typeOptions: { value: string; label: string }[];
}>();

const form = useForm({
    sku: props.product?.sku ?? '',
    name: props.product?.name ?? '',
    type: props.product?.type ?? 'product',
    category_id: props.product?.category_id ?? '',
    unit_id: props.product?.unit_id ?? '',
    purchase_price: String(props.product?.purchase_price ?? '0'),
    sales_price: String(props.product?.sales_price ?? '0'),
    tax_rate_id: props.product?.tax_rate_id ?? '',
    inventory_account_id: props.product?.inventory_account_id ?? props.defaults.inventory ?? '',
    sales_account_id: props.product?.sales_account_id ?? props.defaults.sales ?? '',
    purchase_account_id: props.product?.purchase_account_id ?? props.defaults.purchase ?? '',
    cogs_account_id: props.product?.cogs_account_id ?? props.defaults.cogs ?? '',
    track_inventory: props.product?.track_inventory ?? false,
    low_stock_threshold: props.product?.low_stock_threshold ? String(props.product.low_stock_threshold) : '',
    is_active: props.product?.is_active ?? true,
});

const isService = computed(() => form.type === 'service');

const submit = () => {
    if (props.product) {
        form.put(route('products.update', props.product.id));
    } else {
        form.post(route('products.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Product / Service Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="sku" value="SKU" required />
                    <TextInput id="sku" v-model="form.sku" type="text" class="mt-1 block w-full" placeholder="SKU-0001" required />
                    <InputError :message="form.errors.sku" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="name" value="Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="type" value="Type" required />
                    <select id="type" v-model="form.type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.type" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="category_id" value="Category" />
                    <select id="category_id" v-model="form.category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="category in categoryOptions" :key="category.value" :value="category.value">
                            {{ category.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.category_id" class="mt-1" />
                </div>
                <div v-if="!isService">
                    <InputLabel for="unit_id" value="Unit" />
                    <select id="unit_id" v-model="form.unit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="unit in unitOptions" :key="unit.value" :value="unit.value">
                            {{ unit.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.unit_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="tax_rate_id" value="Tax Rate" />
                    <select id="tax_rate_id" v-model="form.tax_rate_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="rate in taxRateOptions" :key="rate.value" :value="rate.value">
                            {{ rate.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.tax_rate_id" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Pricing</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="purchase_price" value="Purchase Price" />
                    <TextInput id="purchase_price" v-model="form.purchase_price" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <InputError :message="form.errors.purchase_price" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="sales_price" value="Sales Price" />
                    <TextInput id="sales_price" v-model="form.sales_price" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <InputError :message="form.errors.sales_price" class="mt-1" />
                </div>
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 pt-5 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" v-model="form.track_inventory" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                        Track inventory for this item
                    </label>
                    <div v-if="form.track_inventory && !isService" class="w-40">
                        <InputLabel for="low_stock_threshold" value="Low stock alert at" />
                        <TextInput id="low_stock_threshold" v-model="form.low_stock_threshold" type="number" step="0.0001" min="0" class="mt-1 block w-full" placeholder="e.g. 5" />
                        <InputError :message="form.errors.low_stock_threshold" class="mt-1" />
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Default Posting Accounts</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Defaults come from Accounting Settings; override per product if needed.</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="sales_account_id" value="Sales Account" />
                    <select id="sales_account_id" v-model="form.sales_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="account in accountOptions" :key="account.value" :value="account.value">
                            {{ account.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.sales_account_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="purchase_account_id" value="Purchase Account" />
                    <select id="purchase_account_id" v-model="form.purchase_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="account in accountOptions" :key="account.value" :value="account.value">
                            {{ account.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.purchase_account_id" class="mt-1" />
                </div>
                <div v-if="!isService">
                    <InputLabel for="inventory_account_id" value="Inventory Account" />
                    <select id="inventory_account_id" v-model="form.inventory_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="account in accountOptions" :key="account.value" :value="account.value">
                            {{ account.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.inventory_account_id" class="mt-1" />
                </div>
                <div v-if="!isService">
                    <InputLabel for="cogs_account_id" value="COGS Account" />
                    <select id="cogs_account_id" v-model="form.cogs_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="account in accountOptions" :key="account.value" :value="account.value">
                            {{ account.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.cogs_account_id" class="mt-1" />
                </div>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                Active product / service
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('products.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ product ? 'Save Changes' : 'Create Product' }}
            </PrimaryButton>
        </div>
    </form>
</template>