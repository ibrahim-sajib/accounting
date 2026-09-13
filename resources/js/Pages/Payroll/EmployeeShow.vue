<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface SalaryStructureRow {
    basic: number | string;
    house_rent_allowance: number | string;
    medical_allowance: number | string;
    travel_allowance: number | string;
    other_allowance: number | string;
    income_tax_deduction: number | string;
    provident_fund_deduction: number | string;
    other_deduction: number | string;
}

interface RunLineRow {
    id: number;
    run_id: number;
    run_no: string | null;
    period: string | null;
    run_status: string;
    run_date: string;
    gross_pay: number | string;
    deductions_total: number | string;
    net_pay: number | string;
}

const props = defineProps<{
    employee: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        join_date: string;
        is_active: boolean;
        department: { id: number; name: string } | null;
        designation: { id: number; name: string } | null;
        salaryStructure: SalaryStructureRow | null;
    };
    runs: RunLineRow[];
}>();

const page = usePage();
const canUpdate = computed(() => page.props.auth.permissions.includes('payroll.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('payroll.delete') || page.props.auth.user.is_super_admin);

const ss = props.employee.salaryStructure;
const num = (v: number | string | undefined) => Number(v ?? 0);

const allowances = computed(() =>
    ss
        ? num(ss.house_rent_allowance) + num(ss.medical_allowance) + num(ss.travel_allowance) + num(ss.other_allowance)
        : 0
);
const deductions = computed(() =>
    ss ? num(ss.income_tax_deduction) + num(ss.provident_fund_deduction) + num(ss.other_deduction) : 0
);
const gross = computed(() => (ss ? num(ss.basic) + allowances.value : 0));
const net = computed(() => gross.value - deductions.value);

const destroyEmployee = () => {
    if (confirm(`Delete "${props.employee.name}"? This is refused if the employee appears in any payroll run.`)) {
        router.delete(route('payroll.employees.destroy', props.employee.id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader :title="employee.name" :description="`Joined ${formatDate(employee.join_date)}`">
                <template #actions>
                    <Link
                        :href="route('payroll.employees')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        All Employees
                    </Link>
                    <Link
                        v-if="canUpdate"
                        :href="route('payroll.employees.edit', employee.id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="canDelete"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700"
                        @click="destroyEmployee"
                    >
                        Delete
                    </button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Details</h2>
                    </div>
                    <dl class="divide-y divide-gray-100 px-5 py-2 dark:divide-gray-800">
                        <div class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ employee.email ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ employee.phone ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Department</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ employee.department?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Designation</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ employee.designation?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ employee.is_active ? 'Active' : 'Inactive' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Salary Structure</h2>
                    </div>
                    <dl class="divide-y divide-gray-100 px-5 py-2 dark:divide-gray-800">
                        <div v-if="ss" class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Basic</dt>
                            <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(num(ss.basic)) }}</dd>
                        </div>
                        <div v-if="ss" class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">House rent</dt>
                            <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(num(ss.house_rent_allowance)) }}</dd>
                        </div>
                        <div v-if="ss" class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Medical</dt>
                            <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(num(ss.medical_allowance)) }}</dd>
                        </div>
                        <div v-if="ss" class="flex justify-between py-2.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Travel + other allowances</dt>
                            <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(num(ss.travel_allowance) + num(ss.other_allowance)) }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5 text-sm">
                            <dt class="font-medium text-gray-500 dark:text-gray-400">Gross pay</dt>
                            <dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(gross) }}</dd>
                        </div>
                        <div v-if="ss" class="flex justify-between py-2.5 text-sm text-red-600 dark:text-red-400">
                            <dt>Deductions (tax + PF + other)</dt>
                            <dd class="font-mono">{{ formatMoney(deductions) }}</dd>
                        </div>
                        <div class="flex justify-between py-2.5 text-sm">
                            <dt class="font-semibold text-gray-900 dark:text-white">Net pay</dt>
                            <dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(net) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Payroll Run History</h2>
                </div>
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Run</th>
                            <th class="px-4 py-3 font-semibold">Period</th>
                            <th class="px-4 py-3 font-semibold">Run Date</th>
                            <th class="px-4 py-3 text-right font-semibold">Gross</th>
                            <th class="px-4 py-3 text-right font-semibold">Deductions</th>
                            <th class="px-4 py-3 text-right font-semibold">Net Paid</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="line in runs" :key="line.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ line.run_no ?? 'Draft' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ line.period ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(line.run_date) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(line.gross_pay) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(line.deductions_total) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(line.net_pay) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="line.run_status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('payroll.runs.show', line.run_id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View Run
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="runs.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-400">
                                <AppIcon name="payroll" class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                                <span class="mt-2 block">This employee has not appeared in any payroll run yet.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>