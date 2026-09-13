<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';

interface WarehouseOption {
    value: number;
    label: string;
}

interface ProductOption {
    value: number;
    label: string;
    unit?: string | null;
}

interface TransferLine {
    product_id: number | '';
    quantity: string;
}

const props = withDefaults(defineProps<{
    warehouses: WarehouseOption[] | null;
    products: ProductOption[] | null;
    systemQty: Record<string, string>;
    today?: string;
    action: 'create' | 'edit';
    submitUrl: string;
    initial?: {
        transfer_date?: string;
        from_warehouse_id?: number | '';
        to_warehouse_id?: number | '';
        reference?: string;
        memo?: string;
        lines?: TransferLine[];
    };
}>(), {
    today: '',
    initial: () => ({}),
});

const form = ref<{
    transfer_date: string;
    from_warehouse_id: number | '';
    to_warehouse_id: number | '';
    reference: string;
    memo: string;
    lines: TransferLine[];
}>({
    transfer_date: props.initial.transfer_date ?? props.today ?? new Date().toISOString().slice(0, 10),
    from_warehouse_id: props.initial.from_warehouse_id ?? '',
    to_warehouse_id: props.initial.to_warehouse_id ?? '',
    reference: props.initial.reference ?? '',
    memo: props.initial.memo ?? '',
    lines: (props.initial.lines?.length ? props.initial.lines : [{ product_id: '', quantity: '' }]),
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const availableFor = (productId: number | '', warehouseId: number | '') => {
    if (productId === '') return 0;
    return Number(props.systemQty[`${productId}:${warehouseId || ''}`] ?? 0);
};

const addLine = () => {
    form.value.lines.push({ product_id: '', quantity: '' });
};

const removeLine = (index: number) => {
    if (form.value.lines.length > 1) {
        form.value.lines.splice(index, 1);
    }
};

const submit = () => {
    errors.value = {};
    processing.value = true;

    const filteredLines = form.value.lines
        .filter((line) => line.product_id !== '' && line.quantity !== '')
        .map((line) => ({
            product_id: line.product_id,
            quantity: line.quantity,
        }));

    if (filteredLines.length === 0) {
        errors.value.lines = 'Add at least one product line with a quantity.';
        processing.value = false;
        return;
    }

    const payload = {
        transfer_date: form.value.transfer_date,
        from_warehouse_id: form.value.from_warehouse_id,
        to_warehouse_id: form.value.to_warehouse_id,
        reference: form.value.reference,
        memo: form.value.memo,
        lines: filteredLines,
    };

    const options = {
        preserveScroll: true,
        onError: (errs: Record<string, string>) => {
            errors.value = errs;
            processing.value = false;
        },
        onSuccess: () => {
            processing.value = false;
        },
    };

    if (props.action === 'edit') {
        router.put(props.submitUrl, payload, options);
    } else {
        router.post(props.submitUrl, payload, options);
    }
};
</script>

<template>
    <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <InputLabel for="transfer_date" value="Transfer date" />
                <TextInput id="transfer_date" v-model="form.transfer_date" type="date" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="errors.transfer_date" />
            </div>
            <div>
                <InputLabel for="from_warehouse" value="From warehouse" />
                <select
                    id="from_warehouse"
                    v-model="form.from_warehouse_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    required
                >
                    <option value="">Select source…</option>
                    <option v-for="w in warehouses ?? []" :key="w.value" :value="w.value">{{ w.label }}</option>
                </select>
                <InputError class="mt-2" :message="errors.from_warehouse_id" />
            </div>
            <div>
                <InputLabel for="to_warehouse" value="To warehouse" />
                <select
                    id="to_warehouse"
                    v-model="form.to_warehouse_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    required
                >
                    <option value="">Select destination…</option>
                    <option v-for="w in warehouses ?? []" :key="w.value" :value="w.value">{{ w.label }}</option>
                </select>
                <InputError class="mt-2" :message="errors.to_warehouse_id" />
            </div>
            <div>
                <InputLabel for="reference" value="Reference (optional)" />
                <TextInput id="reference" v-model="form.reference" class="mt-1 block w-full" />
                <InputError class="mt-2" :message="errors.reference" />
            </div>
        </div>

        <div class="mt-4">
            <InputLabel value="Memo (optional)" />
            <TextInput v-model="form.memo" class="mt-1 block w-full" placeholder="e.g. Moving stock to the retail warehouse" />
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-200">
                Transfer lines
            </div>
            <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 font-semibold">Product</th>
                        <th class="px-4 py-3 text-right font-semibold">Available at source</th>
                        <th class="px-4 py-3 text-right font-semibold">Quantity to transfer</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(line, index) in form.lines" :key="index">
                        <td class="px-4 py-3">
                            <select
                                v-model="line.product_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <option value="">Select product…</option>
                                <option v-for="p in products ?? []" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">
                            {{ availableFor(line.product_id, form.from_warehouse_id) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <input
                                v-model="line.quantity"
                                type="number"
                                step="0.0001"
                                min="0"
                                :max="String(availableFor(line.product_id, form.from_warehouse_id))"
                                class="w-28 rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="text-gray-400 hover:text-red-600" :disabled="form.lines.length === 1" @click="removeLine(index)">
                                <AppIcon name="trash" class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="addLine">
                    <AppIcon name="plus" class="h-4 w-4" />
                    Add line
                </button>
            </div>
        </div>

        <p v-if="errors.lines" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ errors.lines }}</p>

        <div class="mt-6 flex justify-end gap-3">
            <SecondaryButton type="button" @click="router.visit(route('stock-transfers.index'))">
                Cancel
            </SecondaryButton>
            <PrimaryButton :disabled="processing">
                Save Draft
            </PrimaryButton>
        </div>
    </form>
</template>