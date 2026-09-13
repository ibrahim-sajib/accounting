<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm, router } from '@inertiajs/vue3';
import { formatMoney } from '@/utils/formatMoney';
import { formatDate } from '@/utils/formatDate';
import { ref } from 'vue';

interface ApprovalRow {
    id: number;
    module: string;
    module_label: string;
    summary: string;
    amount: string;
    status: string;
    current_step: number;
    total_steps: number;
    reject_reason: string | null;
    requested_by: string | null;
    decided_by: string | null;
    decided_at: string | null;
    can_approve: boolean;
}

defineProps<{
    pending: ApprovalRow[];
    decided: ApprovalRow[];
    pending_count: number;
}>();

const rejectForm = useForm({ reject_reason: '' });
const rejectTarget = ref<ApprovalRow | null>(null);

const openReject = (row: ApprovalRow) => {
    rejectForm.clearErrors();
    rejectForm.reject_reason = '';
    rejectTarget.value = row;
};

const submitReject = () => {
    if (!rejectTarget.value) return;
    rejectForm.post(route('approvals.reject', rejectTarget.value.id), {
        onSuccess: () => {
            rejectTarget.value = null;
            rejectForm.reset();
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader
                title="Approvals"
                :description="`Documents that exceed the configured posting thresholds (${pending_count} pending).`"
            />

            <div class="mt-6 space-y-6">
                <div class="overflow-x-auto bg-white shadow-sm dark:bg-gray-900 sm:rounded-lg">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        Pending requests
                    </div>
                    <table class="min-w-[760px] lg:min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Document</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Module</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Step</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Requested by</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in pending" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.summary }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.module_label }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ formatMoney(row.amount) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.current_step }} / {{ row.total_steps }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.requested_by }}</td>
                                <td class="px-4 py-2 text-right">
                                    <template v-if="row.can_approve">
                                        <button
                                            class="inline-flex items-center rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700"
                                            @click="router.post(route('approvals.approve', row.id))"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            class="ml-2 inline-flex items-center rounded-md bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700"
                                            @click="openReject(row)"
                                        >
                                            Reject
                                        </button>
                                    </template>
                                    <span v-else class="text-xs text-gray-400">awaiting approver</span>
                                </td>
                            </tr>
                            <tr v-if="pending.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">Nothing awaiting approval.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm dark:bg-gray-900 sm:rounded-lg">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        Recent decisions
                    </div>
                    <table class="min-w-[760px] lg:min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Document</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Decided by</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in decided" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.summary }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ formatMoney(row.amount) }}</td>
                                <td class="px-4 py-2"><StatusBadge :status="row.status" /></td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.decided_by }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.decided_at ? formatDate(row.decided_at) : '—' }}</td>
                            </tr>
                            <tr v-if="decided.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">No decisions recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal :show="rejectTarget !== null" @close="rejectTarget = null">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Reject approval</h2>
                <p v-if="rejectTarget" class="mt-1 text-sm text-gray-500">
                    {{ rejectTarget.summary }} ({{ formatMoney(rejectTarget.amount) }})
                </p>
                <div class="mt-4">
                    <InputLabel for="reject_reason" value="Reason (optional)" />
                    <textarea
                        id="reject_reason"
                        v-model="rejectForm.reject_reason"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        rows="3"
                    ></textarea>
                    <InputError class="mt-2" :message="rejectForm.errors.reject_reason" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton @click="rejectTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton class="!bg-red-600" :disabled="rejectForm.processing" @click="submitReject">Reject</PrimaryButton>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>