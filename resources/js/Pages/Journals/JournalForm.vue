<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { formatMoney } from '@/utils/formatMoney';

interface Option {
    value: number;
    label: string;
}

interface Line {
    account_id: number | string;
    description: string;
    debit: string;
    credit: string;
}

const props = defineProps<{
    periods: Option[];
    accounts: Option[];
    journal?: {
        id?: number;
        journal_date?: string;
        period_id?: number;
        reference?: string | null;
        description?: string | null;
    } | null;
    lines?: Line[];
    today?: string;
    submitLabel?: string;
}>();

const emptyLine = (): Line => ({ account_id: '', description: '', debit: '0', credit: '0' });

const form = useForm<{
    journal_date: string;
    period_id: number | '';
    reference: string;
    description: string;
    lines: Line[];
}>({
    journal_date: props.journal?.journal_date ?? props.today ?? new Date().toISOString().slice(0, 10),
    period_id: props.journal?.period_id ?? props.periods[0]?.value ?? '',
    reference: props.journal?.reference ?? '',
    description: props.journal?.description ?? '',
    lines: props.lines?.map((l) => ({
        account_id: l.account_id,
        description: l.description ?? '',
        debit: String(l.debit ?? '0'),
        credit: String(l.credit ?? '0'),
    })) ?? [emptyLine()],
});

const totals = computed(() => {
    const debit = form.lines.reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0);
    const credit = form.lines.reduce((sum, line) => sum + (parseFloat(line.credit) || 0), 0);
    return { debit, credit, balance: debit - credit };
});

const balanced = computed(() => Math.abs(totals.value.balance) < 0.0001);

const addLine = () => {
    form.lines.push(emptyLine());
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

const lineError = (index: number, field: 'account_id' | 'debit' | 'credit') =>
    (form.errors as Record<string, string>)[`lines.${index}.${field}`];

const submit = () => {
    if (props.journal?.id) {
        form.put(route('journals.update', props.journal.id));
    } else {
        form.post(route('journals.store'));
    }
};
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Journal header</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="journal_date" value="Journal date" />
                    <TextInput
                        id="journal_date"
                        v-model="form.journal_date"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.journal_date" />
                </div>

                <div>
                    <InputLabel for="period_id" value="Accounting period" />
                    <select
                        id="period_id"
                        v-model="form.period_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                    >
                        <option value="" disabled>Select period…</option>
                        <option v-for="period in periods" :key="period.value" :value="period.value">
                            {{ period.label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.period_id" />
                </div>

                <div>
                    <InputLabel for="reference" value="Reference (optional)" />
                    <TextInput
                        id="reference"
                        v-model="form.reference"
                        class="mt-1 block w-full"
                        placeholder="e.g. INV-001"
                    />
                    <InputError class="mt-2" :message="form.errors.reference" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="description" value="Description / memo" />
                <TextInput
                    id="description"
                    v-model="form.description"
                    class="mt-1 block w-full"
                    placeholder="What does this entry record?"
                />
                <InputError class="mt-2" :message="form.errors.description" />
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Journal lines</h2>
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
                    class="grid grid-cols-12 items-start gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                >
                    <div class="col-span-12 sm:col-span-4">
                        <select
                            v-model="line.account_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            required
                        >
                            <option value="" disabled>Select account…</option>
                            <option v-for="account in accounts" :key="account.value" :value="account.value">
                                {{ account.label }}
                            </option>
                        </select>
                        <InputError v-if="lineError(index, 'account_id')" class="mt-1" :message="lineError(index, 'account_id')" />
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <TextInput
                            v-model="line.description"
                            class="block w-full"
                            placeholder="Line note (optional)"
                        />
                    </div>

                    <div class="col-span-3 sm:col-span-2">
                        <TextInput
                            v-model="line.debit"
                            type="number"
                            step="0.0001"
                            min="0"
                            class="block w-full"
                            placeholder="0.00"
                        />
                        <InputError v-if="lineError(index, 'debit')" class="mt-1" :message="lineError(index, 'debit')" />
                    </div>

                    <div class="col-span-3 sm:col-span-2">
                        <TextInput
                            v-model="line.credit"
                            type="number"
                            step="0.0001"
                            min="0"
                            class="block w-full"
                            placeholder="0.00"
                        />
                        <InputError v-if="lineError(index, 'credit')" class="mt-1" :message="lineError(index, 'credit')" />
                    </div>

                    <div class="col-span-12 flex justify-end sm:col-span-1">
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

                <div v-if="form.errors.lines" class="rounded-lg bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30 dark:text-red-300">
                    {{ form.errors.lines }}
                </div>
            </div>

            <div
                class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <div class="grid grid-cols-1 gap-6 text-sm sm:grid-cols-3">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total debit</div>
                        <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ formatMoney(totals.debit) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total credit</div>
                        <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ formatMoney(totals.credit) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Balance</div>
                        <div
                            class="font-mono font-semibold"
                            :class="balanced ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ formatMoney(totals.balance) }}
                        </div>
                    </div>
                </div>

                <div v-if="!balanced" class="text-xs text-amber-600 dark:text-amber-400">
                    Debits must equal credits before the journal can be posted. You can still save it as a draft.
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link
                :href="route('journals.index')"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ submitLabel ?? (journal?.id ? 'Update Draft' : 'Save Draft') }}
            </PrimaryButton>
        </div>
    </form>
</template>