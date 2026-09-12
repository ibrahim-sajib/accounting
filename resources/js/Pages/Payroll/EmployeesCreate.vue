<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

const props = defineProps<{
    departments: { value: number; label: string }[];
    designations: { value: number; label: string }[];
}>();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    join_date: '',
    department_id: '' as number | '',
    designation_id: '' as number | '',
    is_active: true,
    basic: '0',
    house_rent_allowance: '0',
    medical_allowance: '0',
    travel_allowance: '0',
    other_allowance: '0',
    income_tax_deduction: '0',
    provident_fund_deduction: '0',
    other_deduction: '0',
});

const toNum = (v: string) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};

const allowancesPreview = computed(() =>
    toNum(form.house_rent_allowance) + toNum(form.medical_allowance) + toNum(form.travel_allowance) + toNum(form.other_allowance)
);
const deductionsPreview = computed(() =>
    toNum(form.income_tax_deduction) + toNum(form.provident_fund_deduction) + toNum(form.other_deduction)
);
const grossPreview = computed(() => toNum(form.basic) + allowancesPreview.value);
const netPreview = computed(() => grossPreview.value - deductionsPreview.value);

const submit = () => {
    form.post(route('payroll.employees.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl">
            <PageHeader title="Register Employee" description="Employee details plus their salary structure." />

            <form class="space-y-6" @submit.prevent="submit">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Employee Details</h2>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel for="emp_name" value="Full name *" />
                            <TextInput id="emp_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="emp_email" value="Email" />
                            <TextInput id="emp_email" v-model="form.email" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="emp_phone" value="Phone" />
                            <TextInput id="emp_phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="emp_join" value="Join date *" />
                            <TextInput id="emp_join" v-model="form.join_date" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.join_date" />
                        </div>

                        <div>
                            <InputLabel for="emp_department" value="Department *" />
                            <select id="emp_department" v-model="form.department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                <option value="">Select department…</option>
                                <option v-for="d in departments" :key="d.value" :value="d.value">{{ d.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.department_id" />
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel for="emp_designation" value="Designation *" />
                            <select id="emp_designation" v-model="form.designation_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" required>
                                <option value="">Select designation…</option>
                                <option v-for="d in designations" :key="d.value" :value="d.value">{{ d.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.designation_id" />
                        </div>

                        <label class="inline-flex cursor-pointer items-center sm:col-span-2">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-200">Active (included in payroll processing)</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Salary Structure</h2>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="ss_basic" value="Basic salary *" />
                            <TextInput id="ss_basic" v-model="form.basic" type="number" step="0.0001" min="0" class="mt-1 block w-full" required />
                        </div>

                        <div>
                            <InputLabel for="ss_hra" value="House rent allowance" />
                            <TextInput id="ss_hra" v-model="form.house_rent_allowance" type="number" step="0.0001" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel for="ss_medical" value="Medical allowance" />
                            <TextInput id="ss_medical" v-model="form.medical_allowance" type="number" step="0.0001" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel for="ss_travel" value="Travel allowance" />
                            <TextInput id="ss_travel" v-model="form.travel_allowance" type="number" step="0.0001" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel for="ss_other_allow" value="Other allowance" />
                            <TextInput id="ss_other_allow" v-model="form.other_allowance" type="number" step="0.0001" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel for="ss_income_tax" value="Income tax deduction" />
                            <TextInput id="ss_income_tax" v-model="form.income_tax_deduction" type="number" step="0.0001" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel for="ss_pf" value="Provident fund deduction" />
                            <TextInput id="ss_pf" v-model="form.provident_fund_deduction" type="number" step="0.0001" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel for="ss_other_ded" value="Other deduction" />
                            <TextInput id="ss_other_ded" v-model="form.other_deduction" type="number" step="0.0001" min="0" class="mt-1 block w-full" />
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800/50">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Gross pay (basic + allowances)</span>
                            <span class="font-mono font-medium">{{ formatMoney(grossPreview) }}</span>
                        </div>
                        <div class="mt-1 flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Total deductions</span>
                            <span class="font-mono font-medium">{{ formatMoney(deductionsPreview) }}</span>
                        </div>
                        <div class="mt-2 flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900 dark:border-gray-700 dark:text-white">
                            <span>Net pay</span>
                            <span class="font-mono">{{ formatMoney(netPreview) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Link
                        :href="route('payroll.employees')"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">Register Employee</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>