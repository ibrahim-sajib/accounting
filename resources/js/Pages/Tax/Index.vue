<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/formatDate';

interface TaxRate {
    id: number;
    name: string;
    rate_percent: string;
    is_inclusive: boolean;
    input_account_id: number | null;
    output_account_id: number | null;
    effective_date: string;
    input_account?: { code: string; name: string } | null;
    output_account?: { code: string; name: string } | null;
}

interface TaxType {
    id: number;
    name: string;
    rates: TaxRate[];
}

interface AccountOption {
    id: number;
    code: string;
    name: string;
    type: string;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('tax.create') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('tax.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('tax.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    taxTypes: TaxType[];
    accountOptions: AccountOption[];
}>();

const accountLabel = (id: number | null) => {
    if (id === null) return '—';
    const acc = props.accountOptions.find(a => a.id === id);
    return acc ? `${acc.code} · ${acc.name}` : '—';
};

const typeForm = useForm({ name: '' });
const typeModal = ref<{ mode: 'create' } | { mode: 'edit'; taxType: TaxType } | null>(null);

const openCreateType = () => {
    typeForm.reset();
    typeForm.clearErrors();
    typeModal.value = { mode: 'create' };
};

const openEditType = (taxType: TaxType) => {
    typeForm.name = taxType.name;
    typeForm.clearErrors();
    typeModal.value = { mode: 'edit', taxType };
};

const submitType = () => {
    if (typeModal.value?.mode === 'edit') {
        typeForm.put(route('tax.update', typeModal.value.taxType.id), {
            onSuccess: () => { typeModal.value = null; },
        });
    } else {
        typeForm.post(route('tax.store'), {
            onSuccess: () => { typeModal.value = null; },
        });
    }
};

const deleteType = (taxType: TaxType) => {
    if (confirm(`Delete tax type "${taxType.name}"?`)) {
        router.delete(route('tax.destroy', taxType.id), { preserveScroll: true });
    }
};

const rateForm = useForm({
    tax_type_id: null as number | null,
    name: '',
    rate_percent: '',
    is_inclusive: false,
    input_account_id: null as number | null,
    output_account_id: null as number | null,
    effective_date: new Date().toISOString().slice(0, 10),
});

const rateModal = ref<{ mode: 'create'; taxType: TaxType } | { mode: 'edit'; rate: TaxRate; taxType: TaxType } | null>(null);

const openCreateRate = (taxType: TaxType) => {
    rateForm.reset();
    rateForm.clearErrors();
    rateForm.tax_type_id = taxType.id;
    rateModal.value = { mode: 'create', taxType };
};

const openEditRate = (taxType: TaxType, rate: TaxRate) => {
    rateForm.clearErrors();
    rateForm.tax_type_id = taxType.id;
    rateForm.name = rate.name;
    rateForm.rate_percent = rate.rate_percent;
    rateForm.is_inclusive = rate.is_inclusive;
    rateForm.input_account_id = rate.input_account_id;
    rateForm.output_account_id = rate.output_account_id;
    rateForm.effective_date = rate.effective_date;
    rateModal.value = { mode: 'edit', rate, taxType };
};

const submitRate = () => {
    if (!rateModal.value) return;
    if (rateModal.value.mode === 'edit') {
        rateForm.put(route('tax.rates.update', rateModal.value.rate.id), {
            onSuccess: () => { rateModal.value = null; },
        });
    } else {
        rateForm.post(route('tax.rates.store', rateForm.tax_type_id!), {
            onSuccess: () => { rateModal.value = null; },
        });
    }
};

const deleteRate = (rate: TaxRate) => {
    if (confirm(`Delete rate "${rate.name}"?`)) {
        router.delete(route('tax.rates.destroy', rate.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Tax & VAT" description="Tax types (e.g. VAT) and their effective percentage rates.">
                <template #actions>
                    <button
                        v-if="canCreate"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="openCreateType"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Tax Type
                    </button>
                </template>
            </PageHeader>

            <div v-if="taxTypes.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="tax" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No tax types configured yet.</p>
            </div>

            <div v-else class="space-y-4">
                <div v-for="taxType in taxTypes" :key="taxType.id" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <div class="flex items-center gap-3">
                            <AppIcon name="tax" class="h-5 w-5 text-gray-400" />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ taxType.name }}</span>
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ taxType.rates.length }} rate(s)</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="canCreate"
                                type="button"
                                class="rounded-md px-2.5 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
                                @click="openCreateRate(taxType)"
                            >
                                + Add Rate
                            </button>
                            <button
                                v-if="canUpdate"
                                type="button"
                                class="rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                @click="openEditType(taxType)"
                            >
                                Edit
                            </button>
                            <button
                                v-if="canDelete"
                                type="button"
                                class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                                @click="deleteType(taxType)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <table v-if="taxType.rates.length" class="min-w-[760px] w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-2 font-semibold">Rate</th>
                                <th class="px-4 py-2 font-semibold">Percent</th>
                                <th class="px-4 py-2 font-semibold">Inclusive</th>
                                <th class="px-4 py-2 font-semibold">Input Account</th>
                                <th class="px-4 py-2 font-semibold">Output Account</th>
                                <th class="px-4 py-2 font-semibold">Effective</th>
                                <th class="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="rate in taxType.rates" :key="rate.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ rate.name }}</td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ rate.rate_percent }}%</td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ rate.is_inclusive ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-2.5 text-xs text-gray-600 dark:text-gray-300">{{ accountLabel(rate.input_account_id) }}</td>
                                <td class="px-4 py-2.5 text-xs text-gray-600 dark:text-gray-300">{{ accountLabel(rate.output_account_id) }}</td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ formatDate(rate.effective_date) }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="canUpdate"
                                            type="button"
                                            class="text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                            @click="openEditRate(taxType, rate)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            v-if="canDelete"
                                            type="button"
                                            class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                                            @click="deleteRate(rate)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">No rates yet. Click "Add Rate" to configure one.</p>
                </div>
            </div>

            <!-- Tax type modal -->
            <Modal :show="typeModal !== null" @close="typeModal = null">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ typeModal?.mode === 'edit' ? 'Edit Tax Type' : 'New Tax Type' }}
                    </h2>
                    <form @submit.prevent="submitType" class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="type_name" value="Tax Type Name" required />
                            <TextInput id="type_name" v-model="typeForm.name" type="text" class="mt-1 block w-full" placeholder="e.g. VAT, Sales Tax" required />
                            <InputError :message="typeForm.errors.name" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="typeModal = null">
                                Cancel
                            </button>
                            <PrimaryButton :disabled="typeForm.processing">Save Tax Type</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Rate modal -->
            <Modal :show="rateModal !== null" @close="rateModal = null">
                <div v-if="rateModal" class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ rateModal.mode === 'edit' ? 'Edit Rate' : 'Add Rate' }} — {{ rateModal.taxType.name }}
                    </h2>
                    <form @submit.prevent="submitRate" class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="rate_name" value="Rate Name" required />
                                <TextInput id="rate_name" v-model="rateForm.name" type="text" class="mt-1 block w-full" placeholder="Standard Rate 15%" required />
                                <InputError :message="rateForm.errors.name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="rate_percent" value="Rate (%)" required />
                                <TextInput id="rate_percent" v-model="rateForm.rate_percent" type="number" step="0.000001" min="0" class="mt-1 block w-full" required />
                                <InputError :message="rateForm.errors.rate_percent" class="mt-1" />
                            </div>
                        </div>
                        <div>
                            <InputLabel for="effective_date" value="Effective Date" required />
                            <TextInput id="effective_date" v-model="rateForm.effective_date" type="date" class="mt-1 block w-full" required />
                            <InputError :message="rateForm.errors.effective_date" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="input_account_id" value="Input (Purchase) Tax Account" />
                            <select id="input_account_id" v-model="rateForm.input_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— None —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }} ({{ acc.type }})</option>
                            </select>
                            <InputError :message="rateForm.errors.input_account_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="output_account_id" value="Output (Sales) Tax Account" />
                            <select id="output_account_id" v-model="rateForm.output_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— None —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }} ({{ acc.type }})</option>
                            </select>
                            <InputError :message="rateForm.errors.output_account_id" class="mt-1" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" v-model="rateForm.is_inclusive" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                            Tax is included in the price (inclusive)
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="rateModal = null">
                                Cancel
                            </button>
                            <PrimaryButton :disabled="rateForm.processing">Save Rate</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>