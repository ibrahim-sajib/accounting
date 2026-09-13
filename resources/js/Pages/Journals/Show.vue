<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DocumentAttachments from '@/Components/DocumentAttachments.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';
import { formatDate } from '@/utils/formatDate';

interface Journal {
    id: number;
    journal_no: string | null;
    journal_date: string;
    source_type: string;
    source_id: number | null;
    reference: string | null;
    description: string | null;
    status: string;
    posted_at: string | null;
    created_by: number | null;
    posted_by: number | null;
    created_at: string;
    period?: { id: number; name: string } | null;
    origin?: Journal | null;
    reversal?: Journal | null;
    attachments?: {
        id: number;
        original_name: string;
        mime_type: string;
        file_size: number;
        created_at: string;
        uploaded_by: string;
    }[];
}

interface JournalLine {
    id: number;
    account_id: number;
    description: string | null;
    debit: string | number;
    credit: string | number;
    account: { id: number; code: string; name: string; type: string };
}

const props = defineProps<{
    journal: Journal & { lines: JournalLine[] };
}>();

const page = usePage();
const j = computed(() => props.journal);
const isDraft = computed(() => j.value.status === 'draft');
const isPosted = computed(() => j.value.status === 'posted');
const isReversed = computed(() => j.value.status === 'reversed');

const totalDebit = computed(() => {
    return j.value.lines.reduce((sum, l) => sum + (parseFloat(String(l.debit)) || 0), 0);
});
const totalCredit = computed(() => {
    return j.value.lines.reduce((sum, l) => sum + (parseFloat(String(l.credit)) || 0), 0);
});

const canEdit = computed(() => isDraft.value && (page.props.auth.permissions.includes('journal.update') || page.props.auth.user.is_super_admin));
const canPost = computed(() => isDraft.value && (page.props.auth.permissions.includes('journal.post') || page.props.auth.user.is_super_admin));
const canReverse = computed(() => isPosted.value && (page.props.auth.permissions.includes('journal.post') || page.props.auth.user.is_super_admin));
const canDelete = computed(() => isDraft.value && (page.props.auth.permissions.includes('journal.delete') || page.props.auth.user.is_super_admin));

const postJournal = () => {
    if (!confirm('Are you sure you want to post this journal?')) return;
    router.post(route('journals.post', j.value.id), {}, { preserveScroll: true });
};

const reverseJournal = () => {
    if (!confirm('Reverse this posted journal? This creates an equal-and-opposite journal.')) return;
    router.post(route('journals.reverse', j.value.id), {}, { preserveScroll: true });
};

const deleteJournal = () => {
    if (!confirm('Delete this draft journal?')) return;
    router.delete(route('journals.destroy', j.value.id), { preserveScroll: true });
};

const sourceLink = computed(() => {
    if ((j.value.source_type === 'capitalization' || j.value.source_type === 'depreciation' || j.value.source_type === 'asset_disposal') && j.value.source_id) {
        return { label: 'View Asset', href: route('fixed-assets.show', j.value.source_id) };
    }

    if (j.value.source_type === 'sales_invoice' && j.value.source_id) {
        return { label: 'View Invoice', href: route('sales.invoices.show', j.value.source_id) };
    }

    if (j.value.source_type === 'purchase_bill' && j.value.source_id) {
        return { label: 'View Bill', href: route('purchase.bills.show', j.value.source_id) };
    }

    if (j.value.source_type === 'stock_adjustment' && j.value.source_id) {
        return { label: 'View Adjustment', href: route('stock-adjustments.show', j.value.source_id) };
    }

    if (j.value.source_type === 'write_off' && j.value.source_id) {
        return { label: 'View Invoice', href: route('sales.invoices.show', j.value.source_id) };
    }

    if (j.value.source_type === 'payment_application' && j.value.source_id) {
        return { label: 'View Advance', href: route('supplier-advances.show', j.value.source_id) };
    }

    if (j.value.source_type === 'payroll' && j.value.source_id) {
        return { label: 'View Payroll Run', href: route('payroll.runs.show', j.value.source_id) };
    }

    if (j.value.source_type === 'closing' && j.value.source_id) {
        return { label: 'View Fiscal Year', href: route('fiscal-years.index') };
    }

    return null;
});

const sourceTypeLabel: Record<string, string> = {
    manual: 'Manual',
    opening: 'Opening Balance',
    sales_invoice: 'Sales Invoice',
    receipt: 'Receipt',
    receipt_application: 'Advance Applied',
    purchase_bill: 'Purchase Bill',
    payment: 'Supplier Payment',
    payment_application: 'Advance Applied',
    stock_adjustment: 'Stock Adjustment',
    write_off: 'Receivable Write-off',
    capitalization: 'Asset Acquisition',
    depreciation: 'Depreciation',
    asset_disposal: 'Asset Disposal',
    payroll: 'Payroll',
    closing: 'Year-End Closing',
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                :title="j.journal_no ?? 'Draft Journal'"
                description="Journal detail and posting status"
                :show-back="false"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="canEdit"
                            :href="route('journals.edit', j.id)"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <AppIcon name="pencil" class="h-4 w-4" />
                            Edit
                        </Link>
                        <button
                            v-if="canPost"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                            @click="postJournal"
                        >
                            <AppIcon name="check" class="h-4 w-4" />
                            Post Journal
                        </button>
                        <button
                            v-if="canReverse"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/30"
                            @click="reverseJournal"
                        >
                            <AppIcon name="trash" class="h-4 w-4" />
                            Reverse
                        </button>
                        <button
                            v-if="canDelete"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/30"
                            @click="deleteJournal"
                        >
                            <AppIcon name="trash" class="h-4 w-4" />
                            Delete Draft
                        </button>
                    </div>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <!-- Lines -->
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Journal Lines</h2>
                        </div>
                        <table class="min-w-[760px] w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-6 py-3 font-semibold">Account</th>
                                    <th class="px-6 py-3 font-semibold">Memo</th>
                                    <th class="px-6 py-3 text-right font-semibold">Debit</th>
                                    <th class="px-6 py-3 text-right font-semibold">Credit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr
                                    v-for="line in j.lines"
                                    :key="line.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                >
                                    <td class="px-6 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ line.account?.code }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ line.account?.name }}</div>
                                    </td>
                                    <td class="max-w-[20rem] truncate px-6 py-3 text-gray-600 dark:text-gray-300">
                                        {{ line.description ?? '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-right font-mono text-gray-800 dark:text-gray-100">
                                        {{ Number(line.debit) > 0 ? formatMoney(line.debit) : '' }}
                                    </td>
                                    <td class="px-6 py-3 text-right font-mono text-gray-800 dark:text-gray-100">
                                        {{ Number(line.credit) > 0 ? formatMoney(line.credit) : '' }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200 dark:border-gray-800">
                                    <td colspan="2" class="px-6 py-3 text-xs font-semibold uppercase text-gray-500">Totals</td>
                                    <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(totalDebit) }}</td>
                                    <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(totalCredit) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <DocumentAttachments
                        :attachments="j.attachments ?? []"
                        :store-url="route('journals.attachments.store', j.id)"
                        permission="journal.update"
                    />

                    <!-- Reversal / origin link -->
                    <div
                        v-if="j.reversal || (isReversed && j.origin)"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex items-center gap-3 text-sm">
                            <template v-if="isReversed && j.origin">
                                <AppIcon name="arrowLeft" class="h-4 w-4 text-gray-400" />
                                <span class="text-gray-600 dark:text-gray-300">This journal reverses</span>
                                <Link :href="route('journals.show', j.origin.id)" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ j.origin.journal_no }}
                                </Link>
                            </template>
                            <template v-if="j.reversal && isPosted">
                                <AppIcon name="arrowLeft" class="h-4 w-4 text-gray-400" />
                                <span class="text-gray-600 dark:text-gray-300">Reversed by</span>
                                <Link :href="route('journals.show', j.reversal.id)" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ j.reversal.journal_no }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Sidebar meta -->
                <div class="space-y-5">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Summary</h3>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                <dd><StatusBadge :status="j.status" /></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Date</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ formatDate(j.journal_date) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Period</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ j.period?.name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Source</dt>
                                <dd class="flex items-center gap-2 text-gray-800 dark:text-gray-100">
                                    <span>{{ sourceTypeLabel[j.source_type] ?? j.source_type }}</span>
                                    <Link
                                        v-if="sourceLink"
                                        :href="sourceLink.href"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{ sourceLink.label }}
                                    </Link>
                                </dd>
                            </div>
                            <div v-if="j.reference" class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Reference</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ j.reference }}</dd>
                            </div>
                            <div v-if="j.description" class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Memo</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ j.description }}</dd>
                            </div>
                            <div v-if="j.posted_at" class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Posted at</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ formatDate(j.posted_at) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ formatDate(j.created_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>