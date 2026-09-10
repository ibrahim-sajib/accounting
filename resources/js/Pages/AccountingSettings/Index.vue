<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface AccountOption {
    id: number;
    code: string;
    name: string;
    type: string;
}

interface Setting {
    default_sales_account_id: number | null;
    default_purchase_account_id: number | null;
    default_inventory_account_id: number | null;
    default_ar_account_id: number | null;
    default_ap_account_id: number | null;
    default_cash_account_id: number | null;
    default_bank_account_id: number | null;
    default_tax_input_account_id: number | null;
    default_tax_output_account_id: number | null;
    voucher_numbering: Record<string, string> | null;
}

const props = defineProps<{
    setting: Setting;
    accountOptions: AccountOption[];
}>();

const page = usePage();
const canUpdate = computed(() => page.props.auth.permissions.includes('accounting_config.update') || page.props.auth.user.is_super_admin);

const asMap = (s: Record<string, string> | null) => JSON.stringify(s, null, 4);

const form = useForm({
    default_sales_account_id: props.setting.default_sales_account_id,
    default_purchase_account_id: props.setting.default_purchase_account_id,
    default_inventory_account_id: props.setting.default_inventory_account_id,
    default_ar_account_id: props.setting.default_ar_account_id,
    default_ap_account_id: props.setting.default_ap_account_id,
    default_cash_account_id: props.setting.default_cash_account_id,
    default_bank_account_id: props.setting.default_bank_account_id,
    default_tax_input_account_id: props.setting.default_tax_input_account_id,
    default_tax_output_account_id: props.setting.default_tax_output_account_id,
    voucher_numbering: asMap(props.setting.voucher_numbering),
});

const fieldError = (field: string) => form.errors[field as keyof typeof form.errors];

const submit = () => {
    form.put(route('accounting-settings.update'));
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl">
            <PageHeader title="Accounting Settings" description="Default posting accounts and voucher numbering used by the accounting modules." />

            <form class="mt-8 space-y-8" @submit.prevent="submit">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Default Posting Accounts</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        These defaults pre-fill sales, purchase and payment documents. Only postable (leaf) accounts are listed.
                    </p>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <InputLabel for="sales" value="Sales Account" required />
                            <select id="sales" v-model="form.default_sales_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="purchase" value="Purchase Account" required />
                            <select id="purchase" v-model="form.default_purchase_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="inventory" value="Inventory Account" />
                            <select id="inventory" v-model="form.default_inventory_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="ar" value="Accounts Receivable" />
                            <select id="ar" v-model="form.default_ar_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="ap" value="Accounts Payable" />
                            <select id="ap" v-model="form.default_ap_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="cash" value="Cash Account" />
                            <select id="cash" v-model="form.default_cash_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="bank" value="Bank Account" />
                            <select id="bank" v-model="form.default_bank_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="tax_in" value="Tax Input (VAT) Account" />
                            <select id="tax_in" v-model="form.default_tax_input_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="tax_out" value="Tax Output (VAT) Account" />
                            <select id="tax_out" v-model="form.default_tax_output_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option :value="null">— Select —</option>
                                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">{{ acc.code }} · {{ acc.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Voucher Numbering</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Placeholders: <code>{fiscal_year}</code> and <code>{sequence:6}</code> (zero-padded width).
                    </p>
                    <div class="mt-4">
                        <InputLabel for="voucher_numbering" value="Numbering Templates (JSON)" />
                        <textarea
                            id="voucher_numbering"
                            v-model="form.voucher_numbering"
                            rows="8"
                            class="mt-1 block w-full rounded-md border-gray-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        />
                        <InputError :message="fieldError('voucher_numbering')" class="mt-1" />
                    </div>
                </div>

                <div v-if="canUpdate" class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">Save Configuration</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>