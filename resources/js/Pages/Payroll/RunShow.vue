<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface RunLineRow {
    id: number;
    employee_id: number;
    employee_name: string;
    department: string | null;
    designation: string | null;
    gross_pay: number | string;
    allowances_total: number | string;
    deductions_total: number | string;
    net_pay: number | string;
}

interface PaymentRow {
    id: number;
    payment_no: string | null;
    payment_method: string;
    amount: number | string;
    payment_date: string;
    journal_no: string | null;
}

const props = defineProps<{
    run: {
        id: number;
        run_no: string | null;
        period: string | null;
        period_id: number;
        run_date: string;
        status: string;
        total_gross: number | string;
        total_deductions: number | string;
        total_net: number | string;
        journal_id: number | null;
        journal_no: string | null;
        posted_at: string | null;
        employee_count: number;
        paid_total: number | string;
        remaining: number | string;
        paid_state: string | null;
    };
    lines: RunLineRow[];
    payments: PaymentRow[];
    cashAccounts: { value: number; label: string }[];
    bankAccounts: { value: number; label: string }[];
    paymentMethods: { value: string; label: string }[];
}>();

const page = usePage();
const isSuper = computed(() => !!page.props.auth.user.is_super_admin);
const canPost = computed(() => page.props.auth.permissions.includes('payroll.post') || isSuper.value);
const canDelete = computed(() => page.props.auth.permissions.includes('payroll.delete') || isSuper.value);

const isDraft = computed(() => props.run.status === 'draft');
const isPosted = computed(() => props.run.status === 'posted');

const editLines = reactive<Record<number, { gross_pay: string; deductions_total: string }>>({});
const editingIds = reactive(new Set<number>());

const startEdit = (line: RunLineRow) => {
    editLines[line.id] = { gross_pay: String(line.gross_pay ?? '0'), deductions_total: String(line.deductions_total ?? '0') };
    editingIds.add(line.id);
};

const lineNet = (line: RunLineRow) => {
    const e = editLines[line.id];
    if (!e) return Number(line.net_pay ?? 0);
    const g = parseFloat(e.gross_pay);
    const d = parseFloat(e.deductions_total);
    return (Number.isFinite(g) ? g : 0) - (Number.isFinite(d) ? d : 0);
};

const saveLine = (line: RunLineRow) => {
    const e = editLines[line.id];
    if (!e) return;
    router.put(
        route('payroll.runs.lines.update', [props.run.id, line.id]),
        { gross_pay: e.gross_pay, deductions_total: e.deductions_total },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingIds.delete(line.id);
            },
        }
    );
};

const postRun = () => {
    if (confirm('Post this payroll run? This books the salary accrual journal and assigns PR-{year}-####.')) {
        router.post(route('payroll.runs.post', props.run.id), {}, { preserveScroll: true });
    }
};

const destroyRun = () => {
    if (confirm('Delete this draft payroll run? Its lines will be removed.')) {
        router.delete(route('payroll.runs.destroy', props.run.id));
    }
};

const showPayModal = ref(false);
const payForm = useForm({
    payment_method: 'bank',
    cash_account_id: '' as number | '',
    bank_account_id: '' as number | '',
    amount: String(props.run.remaining ?? '0'),
    payment_date: new Date().toISOString().slice(0, 10),
});

watch(showPayModal, (open) => {
    if (open) {
        payForm.clearErrors();
        payForm.reset();
        payForm.amount = String(props.run.remaining ?? '0');
        payForm.payment_date = new Date().toISOString().slice(0, 10);
    }
});

const submitPayment = () => {
    payForm.post(route('payroll.payments.store', props.run.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPayModal.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader :title="run.run_no ?? 'Draft Run'" :description="`Period ${run.period} · run date ${formatDate(run.run_date)}`">
                <template #actions>
                    <Link
                        :href="route('payroll.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        All Runs
                    </Link>
                    <Link
                        v-if="isPosted && run.journal_id"
                        :href="route('journals.show', run.journal_id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        View Journal • {{ run.journal_no }}
                    </Link>
                    <button
                        v-if="isDraft && canDelete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700"
                        @click="destroyRun"
                    >
                        Delete Draft
                    </button>
                    <button
                        v-if="isDraft && canPost"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="postRun"
                    >
                        Post Run
                    </button>
                    <button
                        v-if="isPosted"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700"
                        @click="showPayModal = true"
                    >
                        <AppIcon name="payment" class="h-4 w-4" />
                        Record Salary Payment
                    </button>
                </template>
            </PageHeader>

            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Status</div>
                    <div class="mt-1"><StatusBadge :status="run.status" /></div>
                </div>
                <div v-if="isDraft" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Employees</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ run.employee_count }}</div>
                </div>
                <div v-if="isDraft" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Gross Pay</div>
                    <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.total_gross) }}</div>
                </div>
                <div v-if="isDraft" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Net Payable</div>
                    <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.total_net) }}</div>
                </div>
                <template v-else>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Net Payable</div>
                        <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.total_net) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Paid</div>
                        <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.paid_total) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Remaining</div>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.remaining) }}</span>
                            <StatusBadge :status="run.paid_state ?? ''" />
                        </div>
                    </div>
                </template>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Run Lines ({{ lines.length }})</h2>
                    <p v-if="isDraft" class="text-xs text-gray-400">Draft lines are editable — amounts are recomputed server-side on post.</p>
                    <p v-else-if="run.posted_at" class="text-xs text-gray-400">Posted {{ formatDate(run.posted_at) }}</p>
                </div>
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Employee</th>
                            <th class="px-4 py-3 font-semibold">Department / Designation</th>
                            <th class="px-4 py-3 text-right font-semibold">Gross</th>
                            <th class="px-4 py-3 text-right font-semibold">Deductions</th>
                            <th class="px-4 py-3 text-right font-semibold">Net Pay</th>
                            <th v-if="isDraft" class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="line in lines" :key="line.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('payroll.employees.show', line.employee_id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ line.employee_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ line.department ?? '—' }} / {{ line.designation ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <input
                                    v-if="editingIds.has(line.id)"
                                    v-model="editLines[line.id].gross_pay"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    class="w-28 rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                                <span v-else class="inline-block font-mono text-gray-900 dark:text-white">{{ formatMoney(line.gross_pay) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <input
                                    v-if="editingIds.has(line.id)"
                                    v-model="editLines[line.id].deductions_total"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    class="w-28 rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                                <span v-else class="inline-block font-mono text-gray-900 dark:text-white">{{ formatMoney(line.deductions_total) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono font-semibold" :class="lineNet(line) < 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'">
                                    {{ formatMoney(lineNet(line)) }}
                                </span>
                            </td>
                            <td v-if="isDraft" class="px-4 py-3 text-right">
                                <template v-if="editingIds.has(line.id)">
                                    <button type="button" class="text-xs font-medium text-green-600 hover:text-green-800 dark:text-green-400" @click="saveLine(line)">Save</button>
                                    <button type="button" class="ml-3 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400" @click="editingIds.delete(line.id)">Cancel</button>
                                </template>
                                <button v-else type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="startEdit(line)">Adjust</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">Totals</td>
                            <td class="px-4 py-3" />
                            <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.total_gross) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.total_deductions) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(run.total_net) }}</td>
                            <td v-if="isDraft" class="px-4 py-3" />
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Salary Payments ({{ payments.length }})</h2>
                </div>
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Payment</th>
                            <th class="px-4 py-3 font-semibold">Method</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 text-right font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Journal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="p in payments" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ p.payment_no ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize text-gray-600 dark:text-gray-300">{{ p.payment_method }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(p.payment_date) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(p.amount) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ p.journal_no ?? '—' }}</td>
                        </tr>
                        <tr v-if="payments.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No salary payments recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Modal :show="showPayModal" max-width="md" @close="showPayModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Record Salary Payment</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Books Salary Payable Dr | Cash/Bank Cr and assigns SP-{year}-####.</p>

                    <form class="mt-4 space-y-4" @submit.prevent="submitPayment">
                        <div>
                            <InputLabel for="pay_method" value="Payment method *" />
                            <select id="pay_method" v-model="payForm.payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="payForm.errors.payment_method" />
                        </div>

                        <div v-if="payForm.payment_method === 'cash'">
                            <InputLabel for="pay_cash" value="Cash account *" />
                            <select id="pay_cash" v-model="payForm.cash_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                <option value="">Select cash account…</option>
                                <option v-for="a in cashAccounts" :key="a.value" :value="a.value">{{ a.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="payForm.errors.cash_account_id" />
                        </div>

                        <div v-if="payForm.payment_method === 'bank'">
                            <InputLabel for="pay_bank" value="Bank account *" />
                            <select id="pay_bank" v-model="payForm.bank_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                <option value="">Select bank account…</option>
                                <option v-for="a in bankAccounts" :key="a.value" :value="a.value">{{ a.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="payForm.errors.bank_account_id" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="pay_amount" value="Amount *" />
                                <TextInput id="pay_amount" v-model="payForm.amount" type="number" step="0.0001" min="0.0001" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="payForm.errors.amount" />
                            </div>
                            <div>
                                <InputLabel for="pay_date" value="Payment date *" />
                                <TextInput id="pay_date" v-model="payForm.payment_date" type="date" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="payForm.errors.payment_date" />
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-gray-800/50">
                            <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                <span>Net payable</span>
                                <span class="font-mono font-medium">{{ formatMoney(run.total_net) }}</span>
                            </div>
                            <div class="mt-1 flex justify-between font-semibold text-gray-900 dark:text-gray-200">
                                <span>Remaining after this payment would be</span>
                                <span class="font-mono">{{ formatMoney(Number(run.remaining) - (parseFloat(payForm.amount) || 0)) }}</span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <SecondaryButton @click="showPayModal = false">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="payForm.processing">Record Payment</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>