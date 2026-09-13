<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface AdjustmentLine {
    id: number;
    product_id: number;
    product_name: string | null;
    product_sku: string | null;
    system_qty: number;
    counted_qty: number;
    quantity_delta: number;
    unit_cost: number;
    line_value: number;
}

const page = usePage();
const props = defineProps<{
    adjustment: {
        id: number;
        adjustment_no: string | null;
        adjustment_date: string;
        warehouse: string | null;
        reason: string | null;
        memo: string | null;
        status: string;
        total_value: number;
        posted_at: string | null;
        lines: AdjustmentLine[];
        journal_id?: number | null;
        journal_no?: string | null;
    };
}>();

const isDraft = computed(() => props.adjustment.status === 'draft');

const canPost = computed(() => page.props.auth.permissions.includes('inventory.adjust') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('inventory.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('inventory.delete') || page.props.auth.user.is_super_admin);

const postAdjustment = () => {
    if (confirm('Post this adjustment? A journal (Inventory | Inventory Adjustment Expense) will be posted and it becomes uneditable.')) {
        router.post(route('stock-adjustments.post', props.adjustment.id), {}, { preserveScroll: true });
    }
};

const deleteAdjustment = () => {
    if (confirm('Delete this draft adjustment?')) {
        router.delete(route('stock-adjustments.destroy', props.adjustment.id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                :title="adjustment.adjustment_no ?? 'Draft Adjustment'"
                :description="`${adjustment.reason ?? 'Adjustment'} on ${formatDate(adjustment.adjustment_date)}`"
            >
                <template #actions>
                    <Link
                        v-if="isDraft && canUpdate"
                        :href="route('stock-adjustments.edit', adjustment.id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="isDraft && canDelete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 dark:border-red-900 dark:bg-gray-900"
                        @click="deleteAdjustment"
                    >
                        Delete
                    </button>
                    <button
                        v-if="isDraft && canPost"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="postAdjustment"
                    >
                        Post Adjustment
                    </button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400">Adjustment #</div>
                                <div class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ adjustment.adjustment_no ?? 'Not numbered yet' }}</div>
                                <div class="mt-2"><StatusBadge :status="adjustment.status" /></div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase tracking-wide text-gray-400">Total adjustment value</div>
                                <div class="font-mono text-2xl font-bold text-gray-900 dark:text-white">{{ formatMoney(adjustment.total_value) }}</div>
                            </div>
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-gray-400">Dated</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ formatDate(adjustment.adjustment_date) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Warehouse</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ adjustment.warehouse ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Reason</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ adjustment.reason ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Posting journal</dt>
                                <dd class="font-medium">
                                    <Link
                                        v-if="adjustment.journal_id"
                                        :href="route('journals.show', adjustment.journal_id)"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{ adjustment.journal_no ?? 'Journal' }}
                                    </Link>
                                    <span v-else class="text-gray-400">Not posted</span>
                                </dd>
                            </div>
                        </dl>

                        <p v-if="adjustment.memo" class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ adjustment.memo }}
                        </p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Product</th>
                                    <th class="px-4 py-3 text-right font-semibold">System qty</th>
                                    <th class="px-4 py-3 text-right font-semibold">Counted</th>
                                    <th class="px-4 py-3 text-right font-semibold">Difference</th>
                                    <th class="px-4 py-3 text-right font-semibold">Cost</th>
                                    <th class="px-4 py-3 text-right font-semibold">Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="line in adjustment.lines" :key="line.id">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ line.product_name ?? 'Product' }}</div>
                                        <div v-if="line.product_sku" class="text-xs text-gray-400">{{ line.product_sku }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ line.system_qty }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ line.counted_qty }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="font-mono font-semibold"
                                            :class="line.quantity_delta > 0 ? 'text-green-600 dark:text-green-400' : line.quantity_delta < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400'"
                                        >
                                            {{ line.quantity_delta > 0 ? '+' : '' }}{{ line.quantity_delta }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ formatMoney(line.unit_cost) }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(line.line_value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Posting note</h2>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            Posting records the count difference as a movement and posts a journal:
                            <span class="font-medium text-gray-700 dark:text-gray-200">Inventory</span>
                            Dr/Cr with the opposite on
                            <span class="font-medium text-gray-700 dark:text-gray-200">Inventory Adjustment Expense</span>.
                        </p>
                        <div class="mt-3 flex items-center gap-2 text-xs text-gray-400">
                            <AppIcon name="alert" class="h-4 w-4" />
                            Counts are re-checked against the live ledger at posting time.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>