<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatMoney } from '@/utils/formatMoney';
import { formatDate } from '@/utils/formatDate';

interface Option {
    value: number;
    label: string;
}

interface Row {
    account_id: number | string;
    account_label?: string;
    debit: string;
    credit: string;
    is_new?: boolean;
    removing?: boolean;
}

const props = defineProps<{
    fiscalYear: { id: number; name: string; start_date: string; end_date: string; status: string };
    accounts: Option[];
    entries: Row[];
    posted: boolean;
    locked: boolean;
    postedJournalId: number | null;
}>();

const form = useForm<{ entries: Row[] }>({
    entries: props.entries.map((row) => ({
        account_id: row.account_id,
        account_label: row.account_label,
        debit: Number(row.debit) === 0 ? '0' : String(row.debit),
        credit: Number(row.credit) === 0 ? '0' : String(row.credit),
    })),
});

form.transform((data) => ({
    entries: data.entries
        .filter((row) => String(row.account_id) !== '')
        .map((row) => ({
            account_id: Number(row.account_id),
            debit: row.debit,
            credit: row.credit,
        })),
}));

const readOnly = computed(() => props.posted || props.locked);

const totals = computed(() => {
    const debit = form.entries.reduce((sum, row) => sum + (parseFloat(row.debit) || 0), 0);
    const credit = form.entries.reduce((sum, row) => sum + (parseFloat(row.credit) || 0), 0);
    return { debit, credit, balance: debit - credit };
});

const balanced = computed(() => Math.abs(totals.value.balance) < 0.0001);

const accountSelector = ref('');

const addRow = () => {
    const accountId = Number(accountSelector.value);

    if (!accountId) return;

    if (form.entries.some((row) => Number(row.account_id) === accountId && !row.removing)) {
        return;
    }

    const option = props.accounts.find((a) => a.value === accountId);

    form.entries.push({
        account_id: accountId,
        account_label: option?.label,
        debit: '0',
        credit: '0',
        is_new: true,
    });

    accountSelector.value = '';
};

const removeRow = (index: number) => {
    const row = form.entries[index];

    if (row.is_new) {
        form.entries.splice(index, 1);
        return;
    }

    if (confirm('Remove this account from the opening balance? It will be cleared when you save.')) {
        row.removing = true;
        row.debit = '0';
        row.credit = '0';
    }
};

const saveDraft = () => {
    form.post(route('opening-balances.save', props.fiscalYear.id), { preserveScroll: true });
};

const postBalances = () => {
    if (!confirm('Post opening balances for this fiscal year? This creates an opening journal and cannot be undone.')) return;
    form.post(route('opening-balances.post', props.fiscalYear.id), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl">
            <PageHeader title="Opening Balances" :description="`${fiscalYear.name} · ${formatDate(fiscalYear.start_date)} – ${formatDate(fiscalYear.end_date)}`">
                <template #actions>
                    <Link
                        :href="route('opening-balances.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        <AppIcon name="arrowLeft" class="h-4 w-4" />
                        Back
                    </Link>
                </template>
            </PageHeader>

            <div v-if="posted" class="mb-4 rounded-lg bg-indigo-50 p-4 text-sm text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                These opening balances have been posted.
                <Link
                    v-if="postedJournalId"
                    :href="route('journals.show', postedJournalId)"
                    class="ml-1 font-semibold underline"
                >
                    View posting journal →
                </Link>
            </div>
            <div v-else-if="locked" class="mb-4 rounded-lg bg-gray-100 p-4 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                This fiscal year is closed, so opening balances are read-only.
            </div>

            <!-- Add row (editable only) -->
            <div v-if="!readOnly" class="mb-4 flex items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex-1">
                    <label for="add-account" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Add account</label>
                    <select
                        id="add-account"
                        v-model="accountSelector"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">Select account…</option>
                        <option v-for="account in accounts" :key="account.value" :value="account.value">
                            {{ account.label }}
                        </option>
                    </select>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="addRow"
                >
                    <AppIcon name="plus" class="h-4 w-4" />
                    Add
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Account balances</h2>
                </div>

                <div v-if="form.entries.length === 0" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    No opening balance entries yet{{ readOnly ? '.' : ' — add the accounts to carry forward.' }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-[760px] w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-3 font-semibold">Account</th>
                                <th class="px-6 py-3 text-right font-semibold">Debit</th>
                                <th class="px-6 py-3 text-right font-semibold">Credit</th>
                                <th class="px-6 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="(row, index) in form.entries"
                                :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                :class="row.removing ? 'opacity-40' : ''"
                            >
                                <td class="px-6 py-3">
                                    <select
                                        v-if="row.is_new"
                                        v-model="row.account_id"
                                        class="block w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        required
                                    >
                                        <option v-for="account in accounts" :key="account.value" :value="account.value">
                                            {{ account.label }}
                                        </option>
                                    </select>
                                    <span v-else class="font-medium text-gray-900 dark:text-white">{{ row.account_label }}</span>
                                    <span v-if="row.removing" class="ml-2 text-xs text-red-500">will be removed on save</span>
                                </td>
                                <td class="px-6 py-3">
                                    <TextInput
                                        v-if="!readOnly"
                                        v-model="row.debit"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="block w-full text-right"
                                        placeholder="0.00"
                                    />
                                    <div v-else class="text-right font-mono text-gray-800 dark:text-gray-100">{{ Number(row.debit) > 0 ? formatMoney(row.debit) : '' }}</div>
                                </td>
                                <td class="px-6 py-3">
                                    <TextInput
                                        v-if="!readOnly"
                                        v-model="row.credit"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="block w-full text-right"
                                        placeholder="0.00"
                                    />
                                    <div v-else class="text-right font-mono text-gray-800 dark:text-gray-100">{{ Number(row.credit) > 0 ? formatMoney(row.credit) : '' }}</div>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <button
                                        v-if="!readOnly"
                                        type="button"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30"
                                        title="Remove"
                                        @click="removeRow(index)"
                                    >
                                        <AppIcon name="trash" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 dark:border-gray-800">
                                <td class="px-6 py-3 text-xs font-semibold uppercase text-gray-500">Totals</td>
                                <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(totals.debit) }}</td>
                                <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(totals.credit) }}</td>
                                <td class="px-6 py-3">
                                    <span
                                        class="text-right text-xs font-medium"
                                        :class="balanced ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                    >
                                        {{ balanced ? 'Balanced' : `Diff ${formatMoney(totals.balance)}` }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <InputError v-if="form.errors.entries" class="mt-2" :message="String(form.errors.entries)" />

            <div v-if="!readOnly" class="mt-6 flex flex-wrap items-center justify-end gap-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    :disabled="form.processing"
                    @click="saveDraft"
                >
                    Save Draft
                </button>
                <PrimaryButton :disabled="form.processing || !balanced" @click="postBalances">
                    Post Opening Balances
                </PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>