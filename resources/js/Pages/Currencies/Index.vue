<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    decimal_places: number;
    is_base: boolean;
    is_active: boolean;
    exchange_rates_count: number;
    exchange_rates: { id: number; rate: string; effective_date: string }[];
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('currency.create') || page.props.auth.user.is_super_admin);

defineProps<{
    currencies: {
        data: Currency[];
    };
    filters: { search?: string };
}>();

const showCreate = ref(false);
const showRateModal = ref<null | { currency: Currency; latest_rate: string }>(null);

const form = useForm({
    code: '',
    name: '',
    symbol: '',
    decimal_places: 2,
    is_base: false,
    is_active: true,
});

const rateForm = useForm({
    currency_id: null as number | null,
    rate: '',
    effective_date: new Date().toISOString().slice(0, 10),
});

const submitCreate = () => {
    form.post(route('currencies.store'), {
        onSuccess: () => {
            form.reset();
            showCreate.value = false;
        },
    });
};

const openRateModal = (currency: Currency) => {
    rateForm.clearErrors();
    rateForm.currency_id = currency.id;
    rateForm.rate = '';
    rateForm.effective_date = new Date().toISOString().slice(0, 10);
    showRateModal.value = { currency, latest_rate: currency.exchange_rates?.[0]?.rate ?? '—' };
};

const submitRate = () => {
    rateForm.post(route('currencies.rates.store', rateForm.currency_id!), {
        onSuccess: () => {
            showRateModal.value = null;
            rateForm.reset();
        },
    });
};

const deleteRate = (rateId: number) => {
    if (confirm('Delete this exchange rate?')) {
        router.delete(route('exchange-rates.destroy', rateId), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Currencies" description="Base currency and multi-currency configuration with exchange rates.">
                <template #actions>
                    <button
                        v-if="canCreate"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        @click="showCreate = true"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Currency
                    </button>
                </template>
            </PageHeader>

            <div v-if="currencies.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="currency" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No currencies found.</p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Currency</th>
                            <th class="px-4 py-3 font-semibold">Code</th>
                            <th class="px-4 py-3 font-semibold">Decimals</th>
                            <th class="px-4 py-3 font-semibold">Latest Rate</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="currency in currencies.data" :key="currency.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                                    <span class="text-lg">{{ currency.symbol ?? currency.code }}</span>
                                    <span>{{ currency.name }}</span>
                                    <span
                                        v-if="currency.is_base"
                                        class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                                    >
                                        BASE
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ currency.code }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ currency.decimal_places }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ currency.is_base ? '1.000000' : (currency.exchange_rates?.[0]?.rate ?? '—') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs capitalize" :class="currency.is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                    {{ currency.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="!currency.is_base"
                                    type="button"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    @click="openRateModal(currency)"
                                >
                                    Add Rate
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Create currency modal -->
            <Modal :show="showCreate" @close="showCreate = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">New Currency</h2>
                    <form @submit.prevent="submitCreate" class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="code" value="Currency Code" required />
                            <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" maxlength="3" placeholder="USD" required />
                            <InputError :message="form.errors.code" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="name" value="Currency Name" required />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="US Dollar" required />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="symbol" value="Symbol" />
                                <TextInput id="symbol" v-model="form.symbol" type="text" class="mt-1 block w-full" maxlength="10" placeholder="$" />
                                <InputError :message="form.errors.symbol" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="decimal_places" value="Decimal Places" />
                                <select id="decimal_places" v-model.number="form.decimal_places" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <option :value="0">0</option>
                                    <option :value="2">2</option>
                                    <option :value="3">3</option>
                                    <option :value="4">4</option>
                                </select>
                                <InputError :message="form.errors.decimal_places" class="mt-1" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" v-model="form.is_base" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                            Set as base currency
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showCreate = false">
                                Cancel
                            </button>
                            <PrimaryButton :disabled="form.processing">Save Currency</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Add rate modal -->
            <Modal :show="showRateModal !== null" @close="showRateModal = null">
                <div v-if="showRateModal" class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Exchange Rate — {{ showRateModal.currency.code }}
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Latest rate against base: {{ showRateModal.latest_rate }}
                    </p>
                    <form @submit.prevent="submitRate" class="mt-4 space-y-4">
                        <div>
                            <InputLabel for="rate" value="Rate (1 unit of this currency → base)" required />
                            <TextInput id="rate" v-model="rateForm.rate" type="number" step="0.000001" min="0.000001" class="mt-1 block w-full" required />
                            <InputError :message="rateForm.errors.rate" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="effective_date" value="Effective Date" required />
                            <TextInput id="effective_date" v-model="rateForm.effective_date" type="date" class="mt-1 block w-full" required />
                            <InputError :message="rateForm.errors.effective_date" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white" @click="showRateModal = null">
                                Cancel
                            </button>
                            <PrimaryButton :disabled="rateForm.processing">Save Rate</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>