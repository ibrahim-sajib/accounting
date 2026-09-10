<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';

interface Option { value: string; label: string; }

const props = defineProps<{
    company?: {
        id: number;
        name: string;
        legal_name: string | null;
        country_code: string | null;
        currency_code: string | null;
        tax_registration_no: string | null;
        vat_registration_no: string | null;
        accounting_basis: string;
        status: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        city: string | null;
        state: string | null;
        zip_code: string | null;
        fiscal_year_start: string | null;
        fiscal_year_end: string | null;
    };
    statusOptions: Option[];
    accountingBasisOptions: Option[];
}>();

const form = useForm({
    name: props.company?.name ?? '',
    legal_name: props.company?.legal_name ?? '',
    logo: null as File | null,
    country_code: props.company?.country_code ?? 'BD',
    currency_code: props.company?.currency_code ?? '',
    tax_registration_no: props.company?.tax_registration_no ?? '',
    vat_registration_no: props.company?.vat_registration_no ?? '',
    accounting_basis: props.company?.accounting_basis ?? 'accrual',
    status: props.company?.status ?? 'active',
    email: props.company?.email ?? '',
    phone: props.company?.phone ?? '',
    address: props.company?.address ?? '',
    city: props.company?.city ?? '',
    state: props.company?.state ?? '',
    zip_code: props.company?.zip_code ?? '',
    fiscal_year_start: props.company?.fiscal_year_start ?? '',
    fiscal_year_end: props.company?.fiscal_year_end ?? '',
});

const submit = () => {
    if (props.company) {
        form.put(route('companies.update', props.company.id), {
            forceFormData: true,
        });
    } else {
        form.post(route('companies.store'), {
            forceFormData: true,
        });
    }
};

const onLogoChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    form.logo = input.files?.[0] ?? null;
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">General Information</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Company Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="legal_name" value="Legal Name" />
                    <TextInput id="legal_name" v-model="form.legal_name" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.legal_name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="tax_registration_no" value="Tax Registration No." />
                    <TextInput id="tax_registration_no" v-model="form.tax_registration_no" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.tax_registration_no" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="vat_registration_no" value="VAT Registration No." />
                    <TextInput id="vat_registration_no" v-model="form.vat_registration_no" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.vat_registration_no" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="country_code" value="Country Code" />
                    <TextInput id="country_code" v-model="form.country_code" type="text" class="mt-1 block w-full" maxlength="2" placeholder="BD" />
                    <InputError :message="form.errors.country_code" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="currency_code" value="Base Currency Code" />
                    <TextInput id="currency_code" v-model="form.currency_code" type="text" class="mt-1 block w-full" maxlength="3" placeholder="BDT" />
                    <InputError :message="form.errors.currency_code" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="accounting_basis" value="Accounting Basis" required />
                    <select id="accounting_basis" v-model="form.accounting_basis" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option v-for="option in accountingBasisOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.accounting_basis" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="status" value="Status" required />
                    <select id="status" v-model="form.status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.status" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel for="logo" value="Company Logo" />
                    <input
                        id="logo"
                        type="file"
                        accept="image/*"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-600 dark:text-gray-400 dark:file:bg-indigo-950/50 dark:file:text-indigo-300"
                        @change="onLogoChange"
                    />
                    <InputError :message="form.errors.logo" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Contact Information</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    <InputLabel for="city" value="City" />
                    <TextInput id="city" v-model="form.city" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.city" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="state" value="State / Region" />
                    <TextInput id="state" v-model="form.state" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.state" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="zip_code" value="Zip Code" />
                    <TextInput id="zip_code" v-model="form.zip_code" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.zip_code" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Financial Year Configuration</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="fiscal_year_start" value="Fiscal Year Start" />
                    <TextInput id="fiscal_year_start" v-model="form.fiscal_year_start" type="date" class="mt-1 block w-full" />
                    <InputError :message="form.errors.fiscal_year_start" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="fiscal_year_end" value="Fiscal Year End" />
                    <TextInput id="fiscal_year_end" v-model="form.fiscal_year_end" type="date" class="mt-1 block w-full" />
                    <InputError :message="form.errors.fiscal_year_end" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('companies.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                <AppIcon v-if="form.processing" name="check" class="mr-1.5 h-4 w-4" />
                {{ company ? 'Save Changes' : 'Create Company' }}
            </PrimaryButton>
        </div>
    </form>
</template>