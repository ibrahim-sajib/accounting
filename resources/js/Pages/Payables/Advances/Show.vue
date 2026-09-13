<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface SupplierBrief {
    id: number;
    code?: string | null;
    name: string;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
}

interface AccountBrief {
    id: number;
    code: string;
    name: string;
}

interface Allocation {
    bill_no: string | null;
    bill_date: string | null;
    amount: number;
    applied_state: string | null;
}

interface OutstandingBill {
    id: number;
    bill_no: string | null;
    bill_date: string | null;
    due_date: string | null;
    balance_due: number;
    days_overdue: number;
}

const props = defineProps<{
    advance: {
        id: number;
        payment_no: string | null;
        payment_date: string;
        reference?: string | null;
        memo?: string | null;
        status: string;
        amount: number;
        applied_amount: number;
        balance: number;
        supplier: SupplierBrief | null;
        account: AccountBrief | null;
        allocations: Allocation[];
        journal_id?: number | null;
        journal_no?: string | null;
    };
    outstandingBills: OutstandingBill[];
    today?: string;
}>();

const showApply = ref(false);
const amounts = ref<Record<number, string>>({});

const applyForm = useForm({});

const openApply = () => {
    applyForm.clearErrors();
    amounts.value = {};
    showApply.value = true;
};

const calculateAppliedAmount = computed(() => props.advance.balance);

const allocations = computed(() =>
    props.outstandingBills
        .filter((bill) => Number(amounts.value[bill.id] ?? 0) > 0)
        .map((bill) => ({
            purchase_bill_id: bill.id,
            amount: Number(amounts.value[bill.id] ?? 0),
        })),
);

const total = computed(() => allocations.value.reduce((sum, allocation) => sum + allocation.amount, 0));

const submitApply = () => {
    applyForm
        .transform(() => ({ allocations: allocations.value }))
        .post(route('supplier-advances.apply', props.advance.id), {
            preserveScroll: true,
            onSuccess: () => {
                showApply.value = false;
            },
        });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                :title="advance.payment_no ?? 'Advance'"
                :description="`Paid to ${advance.supplier?.name ?? '—'} on ${formatDate(advance.payment_date)}`"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="openApply"
                    >
                        <AppIcon name="payment" class="h-4 w-4" />
                        Apply to Bills
                    </button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400">Advance #</div>
                                <div class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ advance.payment_no ?? 'Not numbered' }}</div>
                                <div class="mt-2"><StatusBadge :status="advance.status" /></div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase tracking-wide text-gray-400">Unapplied balance</div>
                                <div class="font-mono text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ formatMoney(calculateAppliedAmount) }}</div>
                            </div>
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-gray-400">Amount</dt>
                                <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(advance.amount) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Applied</dt>
                                <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(advance.applied_amount) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Payment account</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ advance.account ? `${advance.account.code} — ${advance.account.name}` : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Posting journal</dt>
                                <dd class="font-medium">
                                    <Link
                                        v-if="advance.journal_id"
                                        :href="route('journals.show', advance.journal_id)"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{ advance.journal_no ?? 'Journal' }}
                                    </Link>
                                    <span v-else class="text-gray-400">Not posted</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Applied allocations</h2>
                        </div>

                        <p v-if="advance.allocations.length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                            This advance has not been applied to any bills yet.
                        </p>

                        <table v-else class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Bill</th>
                                    <th class="px-4 py-3 text-right font-semibold">Date</th>
                                    <th class="px-4 py-3 text-right font-semibold">Amount applied</th>
                                    <th class="px-4 py-3 text-right font-semibold">Bill state</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="(allocation, index) in advance.allocations" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 font-mono font-medium text-gray-900 dark:text-white">{{ allocation.bill_no ?? 'Bill' }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">
                                        {{ allocation.bill_date ? formatDate(allocation.bill_date) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(allocation.amount) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <StatusBadge v-if="allocation.applied_state" :status="allocation.applied_state" />
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Supplier</h2>
                        <div class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <p class="font-medium text-gray-900 dark:text-white">{{ advance.supplier?.name }}</p>
                            <p v-if="advance.supplier?.code" class="font-mono text-xs text-gray-400">{{ advance.supplier.code }}</p>
                            <p v-if="advance.supplier?.email">{{ advance.supplier.email }}</p>
                            <p v-if="advance.supplier?.phone">{{ advance.supplier.phone }}</p>
                            <p v-if="advance.supplier?.address" class="text-xs text-gray-400">{{ advance.supplier.address }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showApply" max-width="2xl" @close="showApply = false">
            <form class="p-6" @submit.prevent="submitApply">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Apply Advance to Bills</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Unapplied balance {{ formatMoney(calculateAppliedAmount) }}. Journal: AP Dr | Supplier Advances Cr.
                </p>

                <p v-if="outstandingBills.length === 0" class="mt-5 rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-400 dark:bg-gray-800/60">
                    No outstanding bills available to apply this advance to.
                </p>

                <table v-else class="mt-5 min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-2 font-semibold">Bill</th>
                            <th class="py-2 text-right font-semibold">Balance due</th>
                            <th class="py-2 pl-2 text-right font-semibold">Apply</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="bill in outstandingBills" :key="bill.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 pr-2">
                                <Link :href="route('purchase.bills.show', bill.id)" class="font-mono font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ bill.bill_no ?? 'Draft' }}
                                </Link>
                                <div class="text-xs text-gray-400">
                                    {{ bill.bill_date ? formatDate(bill.bill_date) : '—' }}
                                    <template v-if="bill.days_overdue > 0">· {{ bill.days_overdue }}d overdue</template>
                                </div>
                            </td>
                            <td class="py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(bill.balance_due) }}</td>
                            <td class="py-3 pl-2 text-right">
                                <input
                                    :id="`apply_${bill.id}`"
                                    v-model="amounts[bill.id]"
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    :max="String(bill.balance_due)"
                                    class="ml-auto block w-32 rounded-md border-gray-300 text-right font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-5 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Total to apply</span>
                        <span class="font-mono text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(total) }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-xs">
                        <span class="text-gray-400">Available balance</span>
                        <span class="font-mono text-gray-600 dark:text-gray-300">{{ formatMoney(calculateAppliedAmount) }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showApply = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="applyForm.processing || allocations.length === 0 || total > calculateAppliedAmount">
                        Apply Advance
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>