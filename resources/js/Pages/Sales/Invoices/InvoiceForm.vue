<script setup lang="ts">
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { formatMoney } from '@/utils/formatMoney';

interface CustomerOption {
    value: number;
    label: string;
    credit_limit: number;
    payment_terms_days: number;
    outstanding: number;
}

interface ProductOption {
    value: number;
    label: string;
    type: string;
    sales_price: number;
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
    unit_price: string;
    discount_amount: string;
    tax_rate_id: number | '';
}

const props = defineProps<{
    customers: CustomerOption[];
    products: ProductOption[];
    taxRates: TaxRateOption[];
    invoice?: {
        id: number;
        customer_id: number;
        invoice_date: string;
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
    unit_price: '0',
    discount_amount: '0',
    tax_rate_id: '',
});

const form = useForm<{
    customer_id: number | '';
    invoice_date: string;
    due_date: string;
    reference: string;
    notes: string;
    lines: Line[];
}>({
    customer_id: props.invoice?.customer_id ?? '',
    invoice_date: props.invoice?.invoice_date ?? props.today ?? new Date().toISOString().slice(0, 10),
    due_date: props.invoice?.due_date ?? '',
    reference: props.invoice?.reference ?? '',
    notes: props.invoice?.notes ?? '',
    lines: props.lines?.map((l) => ({
        product_id: l.product_id,
        description: l.description ?? '',
        quantity: String(l.quantity),
        unit_price: String(l.unit_price),
        discount_amount: String(l.discount_amount ?? '0'),
        tax_rate_id: l.tax_rate_id ?? '',
    })) ?? [emptyLine()],
});

const selectedCustomer = computed(() => props.customers.find((c) => c.value === form.customer_id));

const applyDueDate = () => {
    const days = selectedCustomer.value?.payment_terms_days ?? 0;

    if (days > 0) {
        const date = new Date(`${form.invoice_date}T00:00:00`);
        date.setDate(date.getDate() + days);
        form.due_date = date.toISOString().slice(0, 10);
    } else {
        form.due_date = form.invoice_date;
    }
};

watch(() => form.customer_id, applyDueDate);
watch(() => form.invoice_date, applyDueDate);

const taxPercent = (taxRateId: number | '', fallback?: number | null) => {
    if (taxRateId) {
        return props.taxRates.find((r) => r.value === taxRateId)?.rate_percent ?? 0;
    }

    return fallback ?? 0;
};

const lineTotals = (line: Line) => {
    const quantity = parseFloat(line.quantity) || 0;
    const price = parseFloat(line.unit_price) || 0;
    const discount = parseFloat(line.discount_amount) || 0;
    const total = Math.max(quantity * price - discount, 0);
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

    line.unit_price = String(product.sales_price ?? 0);
    line.tax_rate_id = product.tax_rate_id ?? '';
    line.description = '';
};

const addLine = () => {
    form.lines.push(emptyLine());
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

const lineError = (index: number, field: 'product_id' | 'description' | 'quantity' | 'unit_price' | 'discount_amount' | 'tax_rate_id') =>
    (form.errors as Record<string, string>)[`lines.${index}.${field}`];

const submit = () => {
    if (props.invoice?.id) {
        form.put(route('sales.invoices.update', props.invoice.id));
    } else {
        form.post(route('sales.invoices.store'));
    }
};
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Invoice header</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="customer_id" value="Customer" />
                    <select
                        id="customer_id"
                        v-model="form.customer_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="" disabled>Select customer…</option>
                        <option v-for="customer in customers" :key="customer.value" :value="customer.value">
                            {{ customer.label }}
                        </option>
                    </select>
                    <p
                        v-if="selectedCustomer"
                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Outstanding
                        <span :class="selectedCustomer.outstanding > 0 ? 'font-medium text-red-600 dark:text-red-400' : ''">
                            {{ formatMoney(selectedCustomer.outstanding) }}
                        </span>
                        <template v-if="selectedCustomer.credit_limit > 0">
                            · Credit limit {{ formatMoney(selectedCustomer.credit_limit) }}
                        </template>
                    </p>
                    <InputError class="mt-2" :message="form.errors.customer_id" />
                </div>

                <div>
                    <InputLabel for="invoice_date" value="Invoice date" />
                    <TextInput
                        id="invoice_date"
                        v-model="form.invoice_date"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.invoice_date" />
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
                    placeholder="Customer PO number, e.g. PO-001"
                />
                <InputError class="mt-2" :message="form.errors.reference" />
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Invoice lines</h2>
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
                                v-model="line.unit_price"
                                type="number"
                                step="0.0001"
                                min="0"
                                class="block w-full"
                                placeholder="Price"
                            />
                            <InputError v-if="lineError(index, 'unit_price')" class="mt-1" :message="lineError(index, 'unit_price')" />
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
                <div class="grid grid-cols-1 gap-6 text-sm sm:grid-cols-4">
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
                :href="route('sales.invoices.index')"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ submitLabel ?? (invoice?.id ? 'Update Draft' : 'Save Draft') }}
            </PrimaryButton>
        </div>
    </form>
</template>