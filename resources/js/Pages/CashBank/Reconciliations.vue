<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CashBankTabs from '@/Components/CashBankTabs.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface BankAccountOption {
    id: number;
    account_name: string;
    bank_name: string;
    last_reconciled_date: string | null;
}

interface ImportRow {
    id: number;
    statement_month: string;
    bank_account: { id: number; account_name: string } | null;
    status: string;
    matched_count: number;
    total_count: number;
    completed_at: string | null;
}

const props = defineProps<{
    bankAccounts: BankAccountOption[];
    imports: ImportRow[];
}>();

const page = usePage();
const canReconcile = computed(() => page.props.auth.permissions.includes('bank.reconcile') || page.props.auth.user.is_super_admin);

const showNew = ref(false);

const form = useForm({
    bank_account_id: '' as number | string,
    statement_month: new Date().toISOString().slice(0, 7) + '-01',
    statement_file: null as File | null,
});

const openNew = () => {
    form.clearErrors();
    form.reset();
    form.bank_account_id = '';
    form.statement_month = new Date().toISOString().slice(0, 7) + '-01';
    showNew.value = true;
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.statement_file = input.files?.[0] ?? null;
};

const submit = () => {
    form.post(route('cash-bank.reconciliations.store'), {
        onSuccess: () => {
            showNew.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Bank Reconciliation" description="Import your bank statement CSV, match lines to system transactions, and lock the reconciled period.">
                <template #actions>
                    <button
                        v-if="canReconcile"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="openNew"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Reconciliation
                    </button>
                </template>
            </PageHeader>

            <CashBankTabs active="reconciliation" />

            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 text-xs text-gray-500 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                Expected CSV: <code class="rounded bg-gray-100 px-1 py-0.5 font-mono dark:bg-gray-800">date,description,amount</code> —
                one line per statement row. Amounts may be negative or wrapped in parentheses. Lines are auto-matched by date + amount.
            </div>

            <div v-if="imports.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="bank" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No reconciliations yet.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Bank Account</th>
                            <th class="px-4 py-3 font-semibold">Statement Month</th>
                            <th class="px-4 py-3 font-semibold">Progress</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="item in imports" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ item.bank_account?.account_name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ item.statement_month }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ item.matched_count }} / {{ item.total_count }} matched
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="item.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'"
                                >
                                    {{ item.status === 'completed' ? 'Completed' : 'In Progress' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('cash-bank.reconciliations.show', item.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    Open
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- New reconciliation modal -->
            <Modal :show="showNew" @close="showNew = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">New Reconciliation</h2>
                    <form @submit.prevent="submit" class="mt-4 space-y-4" enctype="multipart/form-data">
                        <div>
                            <InputLabel for="bank_account_id" value="Bank Account" required />
                            <select id="bank_account_id" v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                <option value="">Select bank account…</option>
                                <option v-for="account in (props.bankAccounts as BankAccountOption[])" :key="account.id" :value="account.id">{{ account.account_name }} ({{ account.bank_name }})</option>
                            </select>
                            <InputError :message="form.errors.bank_account_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="statement_month" value="Statement Month" required />
                            <input id="statement_month" v-model="form.statement_month" type="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                            <InputError :message="form.errors.statement_month" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="statement_file" value="Statement CSV" required />
                            <input id="statement_file" type="file" accept=".csv,text/csv" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-300 dark:file:bg-indigo-950/60 dark:file:text-indigo-300" @change="onFileChange" required />
                            <InputError :message="form.errors.statement_file" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showNew = false">Cancel</button>
                            <PrimaryButton :disabled="form.processing">Import &amp; Match</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>