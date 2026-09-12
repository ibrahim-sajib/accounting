<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface Option {
    value: number | string;
    label: string;
}

interface CategoryOption extends Option {
    default_method: string;
    default_useful_life_months: number;
}

const props = defineProps<{
    categories: CategoryOption[];
    cashAccounts: Option[];
    bankAccounts: Option[];
    suppliers: Option[];
    acquisitionMethods: { value: string; label: string }[];
    depreciationMethods: { value: string; label: string }[];
    today?: string;
    asset?: {
        id: number;
        category_id: number;
        asset_code: string;
        name: string;
        acquisition_date: string;
        acquisition_cost: number | string;
        useful_life_months: number;
        method: string;
        location: string | null;
        acquisition_method: string;
        cash_account_id: number | null;
        bank_account_id: number | null;
        supplier_id: number | null;
    } | null;
}>();

const isEdit = !!props.asset;
const today = props.today ?? new Date().toISOString().slice(0, 10);

const form = useForm({
    category_id: props.asset?.category_id ?? ('' as number | ''),
    asset_code: props.asset?.asset_code ?? '',
    name: props.asset?.name ?? '',
    acquisition_date: props.asset?.acquisition_date ?? today,
    acquisition_cost: String(props.asset?.acquisition_cost ?? ''),
    useful_life_months: String(props.asset?.useful_life_months ?? ''),
    method: props.asset?.method ?? '',
    location: props.asset?.location ?? '',
    acquisition_method: props.asset?.acquisition_method ?? 'cash',
    cash_account_id: props.asset?.cash_account_id ?? ('' as number | ''),
    bank_account_id: props.asset?.bank_account_id ?? ('' as number | ''),
    supplier_id: props.asset?.supplier_id ?? ('' as number | ''),
});

const selectedCategory = computed<CategoryOption | undefined>(() => {
    const v = form.category_id;
    if (v === '' || v === null) return undefined;
    return props.categories.find((c) => String(c.value) === String(v));
});

const effectiveMethod = computed(() => {
    return form.method || selectedCategory.value?.default_method || 'straight_line';
});

const effectiveLife = computed<number>(() => {
    const n = parseInt(String(form.useful_life_months), 10);
    if (Number.isFinite(n) && n > 0) return n;
    return selectedCategory.value?.default_useful_life_months ?? 36;
});

const requireCash = computed(() => form.acquisition_method === 'cash');
const requireBank = computed(() => form.acquisition_method === 'bank');
const requirePayable = computed(() => form.acquisition_method === 'payable');

const submit = () => {
    if (isEdit) {
        form.put(route('fixed-assets.update', props.asset!.id), { preserveScroll: true });
    } else {
        form.post(route('fixed-assets.store'), { preserveScroll: true });
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Asset Registration</h2>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="asset_category" value="Asset Category *" />
                    <select
                        id="asset_category"
                        v-model="form.category_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="">Select category…</option>
                        <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.category_id" />
                </div>

                <div>
                    <InputLabel for="asset_code" value="Asset Code *" />
                    <TextInput id="asset_code" v-model="form.asset_code" type="text" class="mt-1 block w-full" placeholder="e.g. FA-0001" required />
                    <InputError class="mt-2" :message="form.errors.asset_code" />
                </div>

                <div>
                    <InputLabel for="asset_name" value="Asset Name *" />
                    <TextInput id="asset_name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="e.g. Delivery Van" required />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="asset_date" value="Acquisition date *" />
                    <TextInput id="asset_date" v-model="form.acquisition_date" type="date" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.acquisition_date" />
                </div>

                <div>
                    <InputLabel for="asset_cost" value="Acquisition cost *" />
                    <TextInput id="asset_cost" v-model="form.acquisition_cost" type="number" step="0.0001" min="0" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.acquisition_cost" />
                </div>

                <div>
                    <InputLabel for="asset_life" value="Useful life (months)" />
                    <TextInput id="asset_life" v-model="form.useful_life_months" type="number" min="1" class="mt-1 block w-full" />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Leave blank to use the category default ({{ selectedCategory?.default_useful_life_months ?? 36 }} months).
                    </p>
                    <InputError class="mt-2" :message="form.errors.useful_life_months" />
                </div>

                <div>
                    <InputLabel for="asset_method" value="Depreciation method" />
                    <select
                        id="asset_method"
                        v-model="form.method"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">Use category default ({{ selectedCategory?.default_method ?? 'straight_line' }})</option>
                        <option v-for="m in depreciationMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Effective method: {{ effectiveMethod.replace('_', ' ') }}</p>
                    <InputError class="mt-2" :message="form.errors.method" />
                </div>

                <div>
                    <InputLabel for="asset_location" value="Location (optional)" />
                    <TextInput id="asset_location" v-model="form.location" type="text" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.location" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Payment for Acquisition</h2>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                Capitalizing books Fixed Asset Dr | Cash/Bank/Accounts Payable Cr.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="asset_acq_method" value="Payment method *" />
                    <select
                        id="asset_acq_method"
                        v-model="form.acquisition_method"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option v-for="m in acquisitionMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.acquisition_method" />
                </div>

                <div v-if="requireCash" class="sm:col-span-1">
                    <InputLabel for="asset_cash" value="Cash account *" />
                    <select id="asset_cash" v-model="form.cash_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                        <option value="">Select cash account…</option>
                        <option v-for="a in cashAccounts" :key="a.value" :value="a.value">{{ a.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.cash_account_id" />
                </div>

                <div v-if="requireBank" class="sm:col-span-1">
                    <InputLabel for="asset_bank" value="Bank account *" />
                    <select id="asset_bank" v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                        <option value="">Select bank account…</option>
                        <option v-for="a in bankAccounts" :key="a.value" :value="a.value">{{ a.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.bank_account_id" />
                </div>

                <div v-if="requirePayable" class="sm:col-span-1">
                    <InputLabel for="asset_supplier" value="Supplier (payable) *" />
                    <select id="asset_supplier" v-model="form.supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                        <option value="">Select supplier…</option>
                        <option v-for="s in suppliers" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.supplier_id" />
                </div>
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800/50">
                <div class="flex items-center justify-between text-gray-600 dark:text-gray-300">
                    <span>Monthly depreciation ({{ effectiveMethod.replace('_', ' ') }}, {{ effectiveLife }} months)</span>
                    <span class="font-mono">{{ formatMoney(parseFloat(String(form.acquisition_cost || '0')) / Math.max(1, effectiveLife)) }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link
                :href="isEdit ? route('fixed-assets.show', asset!.id) : route('fixed-assets.index')"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ isEdit ? 'Save Changes' : 'Register Asset' }}
            </PrimaryButton>
        </div>
    </form>
</template>