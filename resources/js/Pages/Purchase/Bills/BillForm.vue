<script setup lang="ts">
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { formatMoney } from '@/utils/formatMoney';

interface SupplierOption {
    value: number;
    label: string;
    payment_terms_days: number;
    outstanding: number;
}

interface ProductOption {
    value: number;
    label: string;
    type: string;
    purchase_price: number;
    tax_rate_id: number | null;
    unit?: string | null;
}

interface TaxRateOption {
    value: number;
    label: string;
    rate_percent: number;
    is_inclusive: boolean;
}

interface Line {
    product_id: number | '';
    description: string;
    quantity: string;
    unit_cost: string;
    discount_amount: string;
    tax_rate_id: number | '';
}

const props = defineProps<{
    suppliers: SupplierOption[];
    products: ProductOption[];
    taxRates: TaxRateOption[];
    bill?: {
        id: number;
        supplier_id: number;
        bill_date: string;
        due_date?: string | null;
        reference?: string | null;
        notes?: string | null;
    } | null;
    lines?: Line[];
    today?: string;
    submitLabel?: string;
}>();

const emptyLine = (): Line => ({
    product_id: '',
    description: '',
    quantity: '1',
    unit_cost: '0',
    discount_amount: '0',
    tax_rate_id: '',
});

const form = useForm<{
    supplier_id: number | '';
    bill_date: string;
    due_date: string;
    reference: string;
    notes: string;
    lines: Line[];
}>({
    supplier_id: props.bill?.supplier_id ?? '',
    bill_date: props.bill?.bill_date ?? props.today ?? new Date().toISOString().slice(0, 10),
    due_date: props.bill?.due_date ?? '',
    reference: props.bill?.reference ?? '',
    notes: props.bill?.notes ?? '',
    lines: props.lines?.map((l) => ({
        product_id: l.product_id,
        description: l.description ?? '',
        quantity: String(l.quantity),
        unit_cost: String(l.unit_cost),
        discount_amount: String(l.discount_amount ?? '0'),
        tax_rate_id: l.tax_rate_id ?? '',
    })) ?? [emptyLine()],
});

const selectedSupplier = computed(() => props.suppliers.find((s) => s.value === form.supplier_id));

const applyDueDate = () => {
    const days = selectedSupplier.value?.payment_terms_days ?? 0;

    if (days > 0) {
        const date = new Date(`${form.bill_date}T00:00:00`);
        date.setDate(date.getDate() + days);
        form.due_date = date.toISOString().slice(0, 10);
    } else {
        form.due_date = form.bill_date;
    }
};

watch(() => form.supplier_id, applyDueDate);
watch(() => form.bill_date, applyDueDate);

const taxPercent = (taxRateId: number | '', fallback?: number | null) => {
    if (taxRateId) {
        return props.taxRates.find((r) => r.value === taxRateId)?.rate_percent ?? 0;
    }

    return fallback ?? 0;
};

const lineTotals = (line: Line) => {
    const quantity = parseFloat(line.quantity) || 0;
    const cost = parseFloat(line.unit_cost) || 0;
    const discount = parseFloat(line.discount_amount) || 0;
    const total = Math.max(quantity * cost - discount, 0);
    const percent = taxPercent(line.tax_rate_id);
    const tax = total * percent / 100;

    return { total, tax };
};

const totals = computed(() => {
    const subtotal = form.lines.reduce((sum, line) => sum + lineTotals(line).total, 0);
    const discount = form.lines.reduce((sum, line) => sum + (parseFloat(line.discount_amount) || 0), 0);
    const tax = form.lines.reduce((sum, line) => sum + lineTotals(line).tax, 0);

    return { subtotal, discount, tax, grand: subtotal + tax };
});

const onProductChange = (line: Line) => {
    if (!line.product_id) return;

    const product = props.products.find((p) => p.value === line.product_id);

    if (!product) return;

    line.unit_cost = String(product.purchase_price ?? 0);
    line.tax_rate_id = product.tax_rate_id ?? '';
    line.description = '';
};

const addLine = () => {
    form.lines.push(emptyLine());
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

const lineError = (index: number, field: 'product_id' | 'description' | 'quantity' | 'unit_cost' | 'discount_amount' | 'tax_rate_id') =>
    (form.errors as Record<string, string>)[`lines.${index}.${field}`];

const submit = () => {
    if (props.bill?.id) {
        form.put(route('purchase.bills.update', props.bill.id));
    } else {
        form.post(route('purchase.bills.store'));
    }
};
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Bill header</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="supplier_id" value="Supplier" />
                    <select
                        id="supplier_id"
                        v-model="form.supplier_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="" disabled>Select supplier…</option>
                        <option v-for="supplier in suppliers" :key="supplier.value" :value="supplier.value">
                            {{ supplier.label }}
                        </option>
                    </select>
                    <p
                        v-if="selectedSupplier"
                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Outstanding
                        <span :class="selectedSupplier.outstanding > 0 ? 'font-medium text-red-600 dark:text-red-400' : ''">
                            {{ formatMoney(selectedSupplier.outstanding) }}
                        </span>
                    </p>
                    <InputError class="mt-2" :message="form.errors.supplier_id" />
                </div>

                <div>
                    <InputLabel for="bill_date" value="Bill date" />
                    <TextInput
                        id="bill_date"
                        v-model="form.bill_date"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.bill_date" />
                </div>

                <div>
                    <InputLabel for="due_date" value="Due date" />
                    <TextInput
                        id="due_date"
                        v-model="form.due_date"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.due_date" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="reference" value="Reference (optional)" />
                <TextInput
                    id="reference"
                    v-model="form.reference"
                    class="mt-1 block w-full"
                    placeholder="Vendor bill number, e.g. INV-991"
                />
                <InputError class="mt-2" :message="form.errors.reference" />
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Bill lines</h2>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-gray-50 dark:border-gray-700 dark:text-indigo-400 dark:hover:bg-gray-800"
                    @click="addLine"
                >
                    <AppIcon name="plus" class="h-3.5 w-3.5" />
                    Add line
                </button>
            </div>

            <div class="mt-4 space-y-3">
                <div
                    v-for="(line, index) in form.lines"
                    :key="index"
                    class="rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                >
                    <div class="grid grid-cols-12 items-start gap-3">
                        <div class="col-span-12 sm:col-span-4">
                            <select
                                v-model="line.product_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                required
                                @change="onProductChange(line)"
                            >
                                <option value="" disabled>Select product / service…</option>
                                <option v-for="product in products" :key="product.value" :value="product.value">
                                    {{ product.label }}
                                </option>
                            </select>
                            <InputError v-if="lineError(index, 'product_id')" class="mt-1" :message="lineError(index, 'product_id')" />
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <TextInput
                                v-model="line.description"
                                class="block w-full"
                                placeholder="Description (optional)"
                            />
                            <InputError v-if="lineError(index, 'description')" class="mt-1" :message="lineError(index, 'description')" />
                        </div>

                        <div class="col-span-3 sm:col-span-1">
                            <TextInput
                                v-model="line.quantity"
                                type="number"
                                step="any"
                                min="0.000001"
                                class="block w-full"
                                placeholder="Qty"
                            />
                            <InputError v-if="lineError(index, 'quantity')" class="mt-1" :message="lineError(index, 'quantity')" />
                        </div>

                        <div class="col-span-4 sm:col-span-1">
                            <TextInput
                                v-model="line.unit_cost"
                                type="number"
                                step="0.0001"
                                min="0"
                                class="block w-full"
                                placeholder="Cost"
                            />
                            <InputError v-if="lineError(index, 'unit_cost')" class="mt-1" :message="lineError(index, 'unit_cost')" />
                        </div>

                        <div class="col-span-4 sm:col-span-1">
                            <TextInput
                                v-model="line.discount_amount"
                                type="number"
                                step="0.0001"
                                min="0"
                                class="block w-full"
                                placeholder="Disc."
                            />
                            <InputError v-if="lineError(index, 'discount_amount')" class="mt-1" :message="lineError(index, 'discount_amount')" />
                        </div>

                        <div class="col-span-4 sm:col-span-1">
                            <select
                                v-model="line.tax_rate_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <option value="">No tax</option>
                                <option v-for="rate in taxRates" :key="rate.value" :value="rate.value">
                                    {{ rate.label }}
                                </option>
                            </select>
                            <InputError v-if="lineError(index, 'tax_rate_id')" class="mt-1" :message="lineError(index, 'tax_rate_id')" />
                        </div>

                        <div class="col-span-10 sm:col-span-1 sm:text-right">
                            <div class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ formatMoney(lineTotals(line).total) }}
                            </div>
                            <div v-if="lineTotals(line).tax > 0" class="text-xs text-gray-500 dark:text-gray-400">
                                +{{ formatMoney(lineTotals(line).tax) }} tax
                            </div>
                        </div>

                        <div class="col-span-2 flex justify-end sm:col-span-1">
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30"
                                title="Remove line"
                                @click="removeLine(index)"
                            >
                                <AppIcon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="form.errors.lines" class="rounded-lg bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30 dark:text-red-300">
                    {{ form.errors.lines }}
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                <div class="grid grid-cols-4 gap-6 text-sm">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Subtotal</div>
                        <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ formatMoney(totals.subtotal) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Discount</div>
                        <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ formatMoney(totals.discount) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Tax</div>
                        <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ formatMoney(totals.tax) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total</div>
                        <div class="font-mono font-semibold text-green-600 dark:text-green-400">{{ formatMoney(totals.grand) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link
                :href="route('purchase.bills.index')"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ submitLabel ?? (bill?.id ? 'Update Draft' : 'Save Draft') }}
            </PrimaryButton>
        </div>
    </form>
</template>