<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface Warehouse {
    id: number;
    name: string;
    code: string | null;
}

interface TransferLine {
    id: number;
    product_id: number;
    product_name: string | null;
    product_sku: string | null;
    quantity: number;
    unit_cost: number;
    line_value: number;
}

const page = usePage();
const props = defineProps<{
    transfer: {
        id: number;
        transfer_no: string | null;
        transfer_date: string;
        from_warehouse: Warehouse | null;
        to_warehouse: Warehouse | null;
        reference: string | null;
        memo: string | null;
        status: string;
        posted_at: string | null;
        lines: TransferLine[];
    };
}>();

const isDraft = computed(() => props.transfer.status === 'draft');

const canPost = computed(() => page.props.auth.permissions.includes('inventory.transfer') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('inventory.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('inventory.delete') || page.props.auth.user.is_super_admin);

const postTransfer = () => {
    if (confirm('Post this transfer? Stock moves between warehouses with no journal entry and it becomes uneditable.')) {
        router.post(route('stock-transfers.post', props.transfer.id), {}, { preserveScroll: true });
    }
};

const deleteTransfer = () => {
    if (confirm('Delete this draft transfer?')) {
        router.delete(route('stock-transfers.destroy', props.transfer.id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                :title="transfer.transfer_no ?? 'Draft Transfer'"
                :description="`${transfer.from_warehouse?.name ?? '—'} → ${transfer.to_warehouse?.name ?? '—'} on ${formatDate(transfer.transfer_date)}`"
            >
                <template #actions>
                    <Link
                        v-if="isDraft && canUpdate"
                        :href="route('stock-transfers.edit', transfer.id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="isDraft && canDelete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 dark:border-red-900 dark:bg-gray-900"
                        @click="deleteTransfer"
                    >
                        Delete
                    </button>
                    <button
                        v-if="isDraft && canPost"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="postTransfer"
                    >
                        Post Transfer
                    </button>
                </template>
            </PageHeader>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Transfer #</div>
                        <div class="font-mono text-lg font-bold text-gray-900 dark:text-white">{{ transfer.transfer_no ?? 'Not numbered yet' }}</div>
                        <div class="mt-2"><StatusBadge :status="transfer.status" /></div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Route</div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ transfer.from_warehouse?.name ?? '—' }}
                            <AppIcon name="chevronRight" class="h-4 w-4 text-gray-400" />
                            {{ transfer.to_warehouse?.name ?? '—' }}
                        </div>
                    </div>
                </div>

                <dl v-if="transfer.reference || transfer.memo" class="mt-6 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div v-if="transfer.reference">
                        <dt class="text-gray-400">Reference</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ transfer.reference }}</dd>
                    </div>
                    <div v-if="transfer.memo">
                        <dt class="text-gray-400">Memo</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ transfer.memo }}</dd>
                    </div>
                </dl>
            </div>

            <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Product</th>
                            <th class="px-4 py-3 text-right font-semibold">Quantity</th>
                            <th class="px-4 py-3 text-right font-semibold">Cost</th>
                            <th class="px-4 py-3 text-right font-semibold">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="line in transfer.lines" :key="line.id">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ line.product_name ?? 'Product' }}</div>
                                <div v-if="line.product_sku" class="text-xs text-gray-400">{{ line.product_sku }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ line.quantity }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-200">{{ formatMoney(line.unit_cost) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(line.line_value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>