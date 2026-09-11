<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    customer?: {
        id: number;
        code: string;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        tax_no: string | null;
        credit_limit: string | number;
        payment_terms_days: number;
        opening_balance: string | number;
        ar_account_id: number | null;
        is_active: boolean;
    };
    defaultArAccountId: number | null;
    accountOptions: { value: number; label: string }[];
}>();

const form = useForm({
    code: props.customer?.code ?? '',
    name: props.customer?.name ?? '',
    email: props.customer?.email ?? '',
    phone: props.customer?.phone ?? '',
    address: props.customer?.address ?? '',
    tax_no: props.customer?.tax_no ?? '',
    credit_limit: String(props.customer?.credit_limit ?? '0'),
    payment_terms_days: String(props.customer?.payment_terms_days ?? ''),
    opening_balance: String(props.customer?.opening_balance ?? '0'),
    ar_account_id: props.customer?.ar_account_id ?? props.defaultArAccountId ?? '',
    is_active: props.customer?.is_active ?? true,
});

const submit = () => {
    if (props.customer) {
        form.put(route('customers.update', props.customer.id));
    } else {
        form.post(route('customers.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Customer Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="code" value="Customer Code" required />
                    <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" placeholder="CUST-001" required />
                    <InputError :message="form.errors.code" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="name" value="Customer Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="phone" value="Phone" />
                    <TextInput id="phone" v-model="form.phone" type="tel" class="mt-1 block w-full" />
                    <InputError :message="form.errors.phone" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel for="address" value="Address" />
                    <TextInput id="address" v-model="form.address" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.address" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="tax_no" value="Tax / BIN Number" />
                    <TextInput id="tax_no" v-model="form.tax_no" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.tax_no" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="ar_account_id" value="AR Control Account" />
                    <select id="ar_account_id" v-model="form.ar_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— Use company default —</option>
                        <option v-for="account in accountOptions" :key="account.value" :value="account.value">
                            {{ account.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.ar_account_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="payment_terms_days" value="Payment Terms (days)" />
                    <TextInput id="payment_terms_days" v-model="form.payment_terms_days" type="number" min="0" class="mt-1 block w-full" />
                    <InputError :message="form.errors.payment_terms_days" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="credit_limit" value="Credit Limit" />
                    <TextInput id="credit_limit" v-model="form.credit_limit" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <InputError :message="form.errors.credit_limit" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="opening_balance" value="Opening Balance" />
                    <TextInput id="opening_balance" v-model="form.opening_balance" type="number" step="0.01" class="mt-1 block w-full" />
                    <InputError :message="form.errors.opening_balance" class="mt-1" />
                </div>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                Active customer
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('customers.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ customer ? 'Save Changes' : 'Create Customer' }}
            </PrimaryButton>
        </div>
    </form>
</template>