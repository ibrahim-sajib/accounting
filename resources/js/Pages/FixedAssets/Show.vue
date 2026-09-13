<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface Option { value: number; label: string }

const page = usePage();
const canCapitalize = computed(() => page.props.auth.permissions.includes('fixed_asset.update') || page.props.auth.user.is_super_admin);
const canDispose = computed(() => page.props.auth.permissions.includes('fixed_asset.delete') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('fixed_asset.delete') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    asset: {
        id: number;
        asset_code: string;
        name: string;
        acquisition_date: string;
        acquisition_cost: number | string;
        useful_life_months: number;
        method: string;
        location: string | null;
        status: string;
        posted_at: string | null;
        disposal_date: string | null;
        disposal_proceeds: number | string | null;
        category: { id: number; name: string; asset_account_id: number; accumulated_depreciation_account_id: number; depreciation_expense_account_id: number } | null;
        journal: { id: number; journal_no: string | null } | null;
        disposal: { disposal_date: string; proceeds: number | string; book_value: number | string; gain_loss_amount: number | string } | null;
        depreciation_entries: {
            id: number;
            period_id: number;
            monthly_amount: number | string;
            accumulated_amount: number | string;
            period: { name: string } | null;
        }[];
    };
    schedule: { sequence: number; amount: number; accumulated: number }[];
    cashAccounts: Option[];
}>();

const asset = computed(() => props.asset);

const accumulated = computed(() => Number(asset.value.depreciation_entries?.reduce((s, e) => s + Number(e.monthly_amount), 0) ?? 0));
const bookValue = computed(() => Math.max(0, Number(asset.value.acquisition_cost) - accumulated.value));
const lifeUsed = computed(() => asset.value.depreciation_entries?.length ?? 0);
const lifeRemaining = computed(() => Math.max(0, asset.value.useful_life_months - lifeUsed.value));
const isDraft = computed(() => asset.value.status === 'draft');
const isActive = computed(() => asset.value.status === 'active');

const showDispose = ref(false);
const disposeForm = useForm({
    disposal_date: new Date().toISOString().slice(0, 10),
    proceeds: '0',
    proceeds_cash_account_id: '' as number | '',
});

const openDispose = () => {
    disposeForm.clearErrors();
    disposeForm.reset();
    showDispose.value = true;
};

const submitDispose = () => {
    disposeForm.post(route('fixed-assets.dispose', asset.value.id), {
        onSuccess: () => { showDispose.value = false; },
    });
};

const capitalize = () => {
    if (confirm(`Capitalize this asset and post its acquisition journal?`)) {
        router.post(route('fixed-assets.capitalize', asset.value.id), {}, { preserveScroll: true, preserveState: true });
    }
};

const destroy = () => {
    if (confirm(`Delete draft asset "${asset.value.name}"?`)) {
        router.delete(route('fixed-assets.destroy', asset.value.id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader :title="asset.name" :description="asset.asset_code">
                <template #actions>
                    <Link
                        :href="route('fixed-assets.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Back to Assets
                    </Link>
                    <Link
                        v-if="isDraft"
                        :href="route('fixed-assets.edit', asset.id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="isDraft && canCapitalize"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700"
                        @click="capitalize"
                    >
                        <AppIcon name="check" class="h-4 w-4" />
                        Capitalize
                    </button>
                    <button
                        v-if="isDraft && canDelete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 dark:border-red-900 dark:bg-gray-900 dark:text-red-400"
                        @click="destroy"
                    >
                        Delete
                    </button>
                    <button
                        v-if="isActive && canDispose"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700"
                        @click="openDispose"
                    >
                        <AppIcon name="alert" class="h-4 w-4" />
                        Dispose
                    </button>
                </template>
            </PageHeader>

            <div v-if="$page.props.flash?.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
                {{ $page.props.flash.error }}
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Asset Details</h2>
                        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-400">Status</dt><dd class="mt-1"><StatusBadge :status="asset.status" /></dd></div>
                            <div><dt class="text-xs text-gray-400">Category</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ asset.category?.name ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-400">Acquisition date</dt><dd class="mt-1 text-gray-900 dark:text-white">{{ formatDate(asset.acquisition_date) }}</dd></div>
                            <div><dt class="text-xs text-gray-400">Location</dt><dd class="mt-1 text-gray-900 dark:text-white">{{ asset.location || '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-400">Acquisition cost</dt><dd class="mt-1 font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(asset.acquisition_cost) }}</dd></div>
                            <div><dt class="text-xs text-gray-400">Method</dt><dd class="mt-1 capitalize text-gray-900 dark:text-white">{{ asset.method.replace('_', ' ') }}</dd></div>
                            <div><dt class="text-xs text-gray-400">Useful life</dt><dd class="mt-1 text-gray-900 dark:text-white">{{ asset.useful_life_months }} months</dd></div>
                            <div v-if="asset.posted_at"><dt class="text-xs text-gray-400">Capitalized</dt><dd class="mt-1 text-gray-900 dark:text-white">{{ formatDate(asset.posted_at) }}</dd></div>
                        </dl>
                    </div>

                    <div v-if="asset.journal" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Acquisition Journal</h2>
                            <Link :href="route('journals.show', asset.journal.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                {{ asset.journal.journal_no }} →
                            </Link>
                        </div>
                    </div>

                    <div v-if="isActive" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Depreciation History</h2>
                            <span class="text-xs text-gray-400">{{ lifeUsed }} / {{ asset.useful_life_months }} months · {{ lifeRemaining }} remaining</span>
                        </div>
                        <div v-if="asset.depreciation_entries?.length" class="mt-4 max-h-64 overflow-y-auto">
                            <table class="min-w-[760px] w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="pb-2 font-semibold">Period</th>
                                        <th class="pb-2 text-right font-semibold">Amount</th>
                                        <th class="pb-2 text-right font-semibold">Accumulated</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                                    <tr v-for="e in asset.depreciation_entries" :key="e.id">
                                        <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ e.period?.name }}</td>
                                        <td class="py-1.5 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(e.monthly_amount) }}</td>
                                        <td class="py-1.5 text-right font-mono text-gray-500 dark:text-gray-400">{{ formatMoney(e.accumulated_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">No depreciation entries yet.</div>
                    </div>

                    <div v-if="isActive && schedule.length" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Depreciation Schedule (future)</h2>
                        <div class="mt-4 max-h-48 overflow-y-auto">
                            <table class="min-w-[760px] w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="pb-2 font-semibold">#</th>
                                        <th class="pb-2 text-right font-semibold">Amount</th>
                                        <th class="pb-2 text-right font-semibold">Accumulated</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                                    <tr v-for="s in schedule" :key="s.sequence">
                                        <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ s.sequence }}</td>
                                        <td class="py-1.5 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(s.amount) }}</td>
                                        <td class="py-1.5 text-right font-mono text-gray-500 dark:text-gray-400">{{ formatMoney(s.accumulated) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Valuation</h2>
                        <dl class="mt-4 space-y-3">
                            <div class="flex items-center justify-between"><dt class="text-sm text-gray-500 dark:text-gray-400">Acquisition cost</dt><dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(asset.acquisition_cost) }}</dd></div>
                            <div class="flex items-center justify-between"><dt class="text-sm text-gray-500 dark:text-gray-400">Accumulated depreciation</dt><dd class="font-mono text-red-600 dark:text-red-400">-{{ formatMoney(accumulated) }}</dd></div>
                            <div class="flex items-center justify-between border-t border-gray-200 pt-3 dark:border-gray-700"><dt class="text-sm font-medium text-gray-900 dark:text-white">Book value</dt><dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(bookValue) }}</dd></div>
                        </dl>
                    </div>

                    <div v-if="asset.disposal" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disposal</h2>
                        <dl class="mt-4 space-y-3">
                            <div class="flex items-center justify-between"><dt class="text-sm text-gray-500 dark:text-gray-400">Date</dt><dd class="text-gray-900 dark:text-white">{{ formatDate(asset.disposal.disposal_date) }}</dd></div>
                            <div class="flex items-center justify-between"><dt class="text-sm text-gray-500 dark:text-gray-400">Proceeds</dt><dd class="font-mono text-gray-900 dark:text-white">{{ formatMoney(asset.disposal.proceeds) }}</dd></div>
                            <div class="flex items-center justify-between"><dt class="text-sm text-gray-500 dark:text-gray-400">Book value</dt><dd class="font-mono text-gray-900 dark:text-white">{{ formatMoney(asset.disposal.book_value) }}</dd></div>
                            <div class="flex items-center justify-between"><dt class="text-sm text-gray-500 dark:text-gray-400">Gain / Loss</dt><dd class="font-mono font-semibold" :class="Number(asset.disposal.gain_loss_amount) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ Number(asset.disposal.gain_loss_amount) >= 0 ? '+' : '' }}{{ formatMoney(asset.disposal.gain_loss_amount) }}</dd></div>
                        </dl>
                    </div>

                    <div v-if="asset.journal" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <Link :href="route('journals.show', asset.journal.id)" class="flex items-center gap-3 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                            <AppIcon name="journal" class="h-5 w-5" />
                            View Acquisition Journal
                        </Link>
                    </div>
                </div>
            </div>

            <Modal :show="showDispose" max-width="md" @close="showDispose = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Dispose Asset</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Posts: Cash Dr (proceeds), Accumulated Depreciation Dr | Fixed Asset Cr (cost), Gain/Loss.
                    </p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="dispose_date" value="Disposal date *" />
                            <TextInput id="dispose_date" v-model="disposeForm.disposal_date" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="disposeForm.errors.disposal_date" />
                        </div>
                        <div>
                            <InputLabel for="dispose_proceeds" value="Sale proceeds" />
                            <TextInput id="dispose_proceeds" v-model="disposeForm.proceeds" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="disposeForm.errors.proceeds" />
                        </div>
                        <div>
                            <InputLabel for="dispose_cash" value="Cash account (for proceeds)" />
                            <select id="dispose_cash" v-model="disposeForm.proceeds_cash_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                                <option value="">Default (first active cash account)</option>
                                <option v-for="a in cashAccounts" :key="a.value" :value="a.value">{{ a.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="disposeForm.errors.proceeds_cash_account_id" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="showDispose = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="disposeForm.processing" @click="submitDispose">Dispose</PrimaryButton>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>