<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface Option {
    value: number;
    label: string;
}

interface PeriodOption extends Option {
    fiscal_year_id: number;
}

interface InitialLine {
    id?: number;
    account_id: number;
    period_id: number;
    budgeted_amount: number | string;
}

interface LineRow {
    id?: number;
    account_id: number | '';
    period_id: number | '';
    budgeted_amount: string;
}

const props = defineProps<{
    fiscalYears: Option[];
    accounts: Option[];
    periods: PeriodOption[];
    initialLines?: InitialLine[];
    initialBudget?: { name?: string; fiscal_year_id?: number | ''; notes?: string | null } | null;
    budget?: { id: number } | null;
}>();

const isEdit = !!props.budget;

const form = useForm({
    name: props.initialBudget?.name ?? '',
    fiscal_year_id: (props.initialBudget?.fiscal_year_id ?? '') as number | '',
    notes: props.initialBudget?.notes ?? '',
    lines: (props.initialLines?.length
        ? props.initialLines.map((l) => ({
            id: l.id,
            account_id: l.account_id,
            period_id: l.period_id,
            budgeted_amount: String(l.budgeted_amount ?? ''),
        }))
        : [
            { account_id: '' as number | '', period_id: '' as number | '', budgeted_amount: '0' },
        ]) as LineRow[],
});

const visiblePeriods = computed(() =>
    props.periods.filter((p) => p.fiscal_year_id === form.fiscal_year_id)
);

const totalBudgeted = computed(() =>
    form.lines.reduce((sum, l) => sum + (parseFloat(l.budgeted_amount) || 0), 0)
);

const addLine = () => {
    form.lines.push({ account_id: '' as number | '', period_id: '' as number | '', budgeted_amount: '0' });
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

const lineError = (index: number, field: string): string | undefined =>
    (form.errors as Record<string, string>)[`lines.${index}.${field}`] ?? undefined;

const submit = () => {
    const lines = form.lines
        .filter((l) => l.account_id !== '' && l.period_id !== '')
        .map((l) => ({
            account_id: l.account_id,
            period_id: l.period_id,
            budgeted_amount: l.budgeted_amount === '' ? '0' : l.budgeted_amount,
        }));

    if (lines.length === 0) {
        form.clearErrors();
        (form as any).errors.lines = 'Add at least one budget line.';
        return;
    }

    if (isEdit && props.budget) {
        form.transform((data) => ({ ...data, lines })).put(route('budgets.update', props.budget.id), {
            preserveScroll: true,
        });
    } else {
        form.transform((data) => ({ ...data, lines })).post(route('budgets.store'), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ isEdit ? 'Budget Details' : 'New Annual Budget' }}
            </h2>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="budget_name" value="Budget name *" />
                    <TextInput id="budget_name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="e.g. FY 2026 Operating Budget" required />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="budget_fy" value="Fiscal year *" />
                    <select
                        id="budget_fy"
                        v-model="form.fiscal_year_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        required
                        :disabled="isEdit"
                    >
                        <option value="">Select fiscal year…</option>
                        <option v-for="f in fiscalYears" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.fiscal_year_id" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="budget_notes" value="Notes" />
                    <textarea
                        id="budget_notes"
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    />
                    <InputError class="mt-2" :message="form.errors.notes" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Budget Lines</h2>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    Total budgeted: <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(totalBudgeted) }}</span>
                </div>
            </div>

            <p v-if="form.errors.lines" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.lines }}</p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-3 font-semibold">Account</th>
                            <th class="py-2 pr-3 font-semibold">Period</th>
                            <th class="py-2 pr-3 text-right font-semibold">Budgeted Amount</th>
                            <th class="py-2" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="(line, index) in form.lines" :key="index">
                            <td class="py-2 pr-3">
                                <select
                                    :id="`line_${index}_account`"
                                    v-model="line.account_id"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                >
                                    <option value="">Select account…</option>
                                    <option v-for="a in accounts" :key="a.value" :value="a.value">{{ a.label }}</option>
                                </select>
                                <InputError class="mt-1" :message="lineError(index, 'account_id')" />
                            </td>
                            <td class="py-2 pr-3">
                                <select
                                    :id="`line_${index}_period`"
                                    v-model="line.period_id"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                >
                                    <option value="">Select period…</option>
                                    <option v-for="p in visiblePeriods" :key="p.value" :value="p.value">{{ p.label }}</option>
                                </select>
                                <InputError class="mt-1" :message="lineError(index, 'period_id')" />
                            </td>
                            <td class="py-2 pr-3">
                                <TextInput
                                    :id="`line_${index}_amount`"
                                    v-model="line.budgeted_amount"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    class="block w-full text-right"
                                />
                                <InputError class="mt-1" :message="lineError(index, 'budgeted_amount')" />
                            </td>
                            <td class="py-2 text-center">
                                <button
                                    type="button"
                                    class="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30"
                                    @click="removeLine(index)"
                                >
                                    <AppIcon name="trash" class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button
                type="button"
                class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                @click="addLine"
            >
                <AppIcon name="plus" class="h-4 w-4" />
                Add line
            </button>
        </div>

        <div class="flex items-center justify-end gap-3">
            <InputError class="mt-2" :message="form.errors.lines" />
            <PrimaryButton :disabled="form.processing">
                {{ form.processing ? 'Saving…' : isEdit ? 'Save Budget' : 'Create Budget' }}
            </PrimaryButton>
        </div>
    </form>
</template>