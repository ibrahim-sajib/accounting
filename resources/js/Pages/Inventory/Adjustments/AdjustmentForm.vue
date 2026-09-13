<script setup lang="ts">
import { computed, ref } from 'vue';
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

interface ReasonOption {
    value: string;
    label: string;
}

interface AdjustmentLine {
    product_id: number | '';
    counted_qty: string;
}

const props = withDefaults(defineProps<{
    warehouses: WarehouseOption[] | null;
    products: ProductOption[] | null;
    systemQty: Record<string, string>;
    reasons: ReasonOption[] | null;
    today?: string;
    action: 'create' | 'edit';
    submitUrl: string;
    initial?: {
        adjustment_date?: string;
        warehouse_id?: number | '';
        reason?: string;
        memo?: string;
        lines?: AdjustmentLine[];
    };
}>(), {
    today: '',
    initial: () => ({}),
});

const form = ref<{
    adjustment_date: string;
    warehouse_id: number | '';
    reason: string;
    memo: string;
    lines: AdjustmentLine[];
}>({
    adjustment_date: props.initial.adjustment_date ?? props.today ?? new Date().toISOString().slice(0, 10),
    warehouse_id: props.initial.warehouse_id ?? '',
    reason: props.initial.reason ?? '',
    memo: props.initial.memo ?? '',
    lines: (props.initial.lines?.length ? props.initial.lines : [{ product_id: '', counted_qty: '0' }]),
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const systemQtyFor = (productId: number | '', warehouseId: number | '') => {
    if (productId === '') return 0;
    const key = `${productId}:${warehouseId || ''}`;
    return Number(props.systemQty[key] ?? 0);
};

const deltaFor = (line: AdjustmentLine, warehouseId: number | '') => {
    if (line.product_id === '') return 0;
    const counted = Number(line.counted_qty) || 0;
    return counted - systemQtyFor(line.product_id, warehouseId);
};

const lineProduct = (id: number | '') => (props.products ?? []).find((p) => p.value === id);

const addLine = () => {
    form.value.lines.push({ product_id: '', counted_qty: '0' });
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
        .filter((line) => line.product_id !== '')
        .map((line) => ({
            product_id: line.product_id,
            counted_qty: line.counted_qty === '' ? '0' : line.counted_qty,
        }));

    const payload = {
        adjustment_date: form.value.adjustment_date,
        warehouse_id: form.value.warehouse_id,
        reason: form.value.reason,
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

    if (filteredLines.length === 0) {
        errors.value.lines = 'Add at least one product line.';
        processing.value = false;
        return;
    }

    if (props.action === 'edit') {
        router.put(props.submitUrl, payload, options);
    } else {
        router.post(props.submitUrl, payload, options);
    }
};
</script>

<template>
    <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <InputLabel for="adjustment_date" value="Adjustment date" />
                <TextInput id="adjustment_date" v-model="form.adjustment_date" type="date" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="errors.adjustment_date" />
            </div>
            <div>
                <InputLabel for="warehouse" value="Warehouse" />
                <select
                    id="warehouse"
                    v-model="form.warehouse_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    required
                >
                    <option value="">Select warehouse…</option>
                    <option v-for="w in warehouses ?? []" :key="w.value" :value="w.value">{{ w.label }}</option>
                </select>
                <InputError class="mt-2" :message="errors.warehouse_id" />
            </div>
            <div>
                <InputLabel for="reason" value="Reason" />
                <select
                    id="reason"
                    v-model="form.reason"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    required
                >
                    <option value="">Select reason…</option>
                    <option v-for="r in reasons ?? []" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
                <InputError class="mt-2" :message="errors.reason" />
            </div>
        </div>

        <div class="mt-4">
            <InputLabel value="Memo (optional)" />
            <TextInput v-model="form.memo" class="mt-1 block w-full" placeholder="e.g. Damaged goods found during weekly stock check" />
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-200">
                Count lines
            </div>
            <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 font-semibold">Product</th>
                        <th class="px-4 py-3 text-right font-semibold">System qty</th>
                        <th class="px-4 py-3 text-right font-semibold">Counted qty</th>
                        <th class="px-4 py-3 text-right font-semibold">Difference</th>
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
                            {{ systemQtyFor(line.product_id, form.warehouse_id) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <input
                                v-model="line.counted_qty"
                                type="number"
                                step="0.0001"
                                min="0"
                                class="w-28 rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span
                                class="font-mono font-semibold"
                                :class="deltaFor(line, form.warehouse_id) > 0 ? 'text-green-600 dark:text-green-400' : deltaFor(line, form.warehouse_id) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400'"
                            >
                                {{ deltaFor(line, form.warehouse_id) > 0 ? '+' : '' }}{{ deltaFor(line, form.warehouse_id) }}
                            </span>
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
            <SecondaryButton type="button" @click="router.visit(route('stock-adjustments.index'))">
                Cancel
            </SecondaryButton>
            <PrimaryButton :disabled="processing">
                {{ action === 'edit' ? 'Save Draft' : 'Save Draft' }}
            </PrimaryButton>
        </div>
    </form>
</template>