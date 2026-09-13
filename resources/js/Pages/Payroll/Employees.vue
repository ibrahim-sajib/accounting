<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PayrollTabs from '@/Components/PayrollTabs.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/formatDate';
import { formatMoney } from '@/utils/formatMoney';

interface EmployeeRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    join_date: string;
    is_active: boolean;
    department: string | null;
    designation: string | null;
    gross_pay: number | string | null;
    net_pay: number | string | null;
}

interface NameRow {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    employees: {
        data: EmployeeRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string; department_id?: string; designation_id?: string; inactive?: string };
    departments: NameRow[];
    designations: NameRow[];
}>();

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('payroll.create') || page.props.auth.user.is_super_admin);
const canUpdate = computed(() => page.props.auth.permissions.includes('payroll.update') || page.props.auth.user.is_super_admin);
const canDelete = computed(() => page.props.auth.permissions.includes('payroll.delete') || page.props.auth.user.is_super_admin);

const allDepartments = computed(() => props.departments);
const allDesignations = computed(() => props.designations);

const activeDepts = computed(() => props.departments.filter((d) => d.is_active));
const activeDesignations = computed(() => props.designations.filter((d) => d.is_active));

const showDeptModal = ref(false);
const showDesigModal = ref(false);

const deptForm = useForm({ name: '' });
const desigForm = useForm({ name: '' });
const editingDeptId = ref<number | null>(null);
const editingDesigId = ref<number | null>(null);
const deptEditName = ref('');
const desigEditName = ref('');

const openDeptModal = () => {
    deptForm.reset();
    deptForm.clearErrors();
    editingDeptId.value = null;
    showDeptModal.value = true;
};

const openDesigModal = () => {
    desigForm.reset();
    desigForm.clearErrors();
    editingDesigId.value = null;
    showDesigModal.value = true;
};

const startEditDept = (d: NameRow) => {
    editingDeptId.value = d.id;
    deptEditName.value = d.name;
};

const saveDept = (d: NameRow) => {
    const name = deptEditName.value.trim();
    if (!name) return;
    router.put(route('payroll.departments.update', d.id), { name }, { preserveScroll: true });
    editingDeptId.value = null;
};

const destroyDept = (d: NameRow) => {
    if (confirm(`Delete department "${d.name}"?${d.is_active ? ' It will be deactivated if in use.' : ''}`)) {
        router.delete(route('payroll.departments.destroy', d.id), { preserveScroll: true });
    }
};

const startEditDesig = (d: NameRow) => {
    editingDesigId.value = d.id;
    desigEditName.value = d.name;
};

const saveDesig = (d: NameRow) => {
    const name = desigEditName.value.trim();
    if (!name) return;
    router.put(route('payroll.designations.update', d.id), { name }, { preserveScroll: true });
    editingDesigId.value = null;
};

const destroyDesig = (d: NameRow) => {
    if (confirm(`Delete designation "${d.name}"?${d.is_active ? ' It will be deactivated if in use.' : ''}`)) {
        router.delete(route('payroll.designations.destroy', d.id), { preserveScroll: true });
    }
};

const createDept = () => {
    deptForm.post(route('payroll.departments.store'), {
        preserveScroll: true,
        onSuccess: () => deptForm.reset(),
    });
};

const createDesig = () => {
    desigForm.post(route('payroll.designations.store'), {
        preserveScroll: true,
        onSuccess: () => desigForm.reset(),
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Employees" :description="`${employees.total ?? 0} employee(s)`">
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @click="openDeptModal"
                    >
                        <AppIcon name="settings" class="h-4 w-4" />
                        Departments
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @click="openDesigModal"
                    >
                        <AppIcon name="settings" class="h-4 w-4" />
                        Designations
                    </button>
                    <Link
                        v-if="canCreate"
                        :href="route('payroll.employees.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Employee
                    </Link>
                </template>
            </PageHeader>

            <PayrollTabs active="employees" />

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput :model-value="filters?.search" placeholder="Search by name, email or phone..." />

                <select
                    :value="filters?.department_id ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-48"
                    @change="(e: Event) => router.get(route('payroll.employees'), { department_id: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All departments</option>
                    <option v-for="d in allDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>

                <select
                    :value="filters?.designation_id ?? ''"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 sm:w-48"
                    @change="(e: Event) => router.get(route('payroll.employees'), { designation_id: (e.target as HTMLSelectElement).value })"
                >
                    <option value="">All designations</option>
                    <option v-for="d in allDesignations" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>

                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input
                        type="checkbox"
                        :checked="filters?.inactive === '1'"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        @change="(e: Event) => router.get(route('payroll.employees'), { inactive: (e.target as HTMLInputElement).checked ? '1' : '' })"
                    />
                    Inactive only
                </label>
            </div>

            <div v-if="employees.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="payroll" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No employees found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Employee</th>
                            <th class="px-4 py-3 font-semibold">Department</th>
                            <th class="px-4 py-3 font-semibold">Designation</th>
                            <th class="px-4 py-3 font-semibold">Joined</th>
                            <th class="px-4 py-3 text-right font-semibold">Gross</th>
                            <th class="px-4 py-3 text-right font-semibold">Net</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="e in employees.data" :key="e.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <Link :href="route('payroll.employees.show', e.id)" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ e.name }}
                                </Link>
                                <div class="text-xs text-gray-400">{{ e.email ?? e.phone ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ e.department ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ e.designation ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(e.join_date) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(e.gross_pay ?? 0) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(e.net_pay ?? 0) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="e.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                                    {{ e.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('payroll.employees.show', e.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="employees.links" />
            </div>

            <Modal :show="showDeptModal" max-width="md" @close="showDeptModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Departments</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Used on the employee form and payroll run.</p>

                    <form class="mt-4 flex gap-2" @submit.prevent="createDept">
                        <TextInput id="dept_name" v-model="deptForm.name" type="text" class="flex-1" placeholder="New department name" required />
                        <PrimaryButton :disabled="deptForm.processing">Add</PrimaryButton>
                    </form>
                    <InputError class="mt-2" :message="deptForm.errors.name" />

                    <ul class="mt-4 divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <li v-for="d in allDepartments" :key="d.id" class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <template v-if="editingDeptId !== d.id">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ d.name }}</span>
                                    <span v-if="!d.is_active" class="ml-2 text-xs text-gray-400">inactive</span>
                                </template>
                                <input
                                    v-else
                                    v-model="deptEditName"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                            </div>
                            <div v-if="canUpdate || canDelete" class="flex shrink-0 gap-3">
                                <template v-if="editingDeptId === d.id">
                                    <button type="button" class="text-xs font-medium text-green-600 hover:text-green-800 dark:text-green-400" @click="saveDept(d)">Save</button>
                                    <button type="button" class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400" @click="editingDeptId = null">Cancel</button>
                                </template>
                                <template v-else>
                                    <button v-if="canUpdate" type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="startEditDept(d)">Edit</button>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="destroyDept(d)">Delete</button>
                                </template>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-4 flex justify-end">
                        <SecondaryButton @click="showDeptModal = false">Close</SecondaryButton>
                    </div>
                </div>
            </Modal>

            <Modal :show="showDesigModal" max-width="md" @close="showDesigModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Designations</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Used on the employee form and payroll run.</p>

                    <form class="mt-4 flex gap-2" @submit.prevent="createDesig">
                        <TextInput id="desig_name" v-model="desigForm.name" type="text" class="flex-1" placeholder="New designation name" required />
                        <PrimaryButton :disabled="desigForm.processing">Add</PrimaryButton>
                    </form>
                    <InputError class="mt-2" :message="desigForm.errors.name" />

                    <ul class="mt-4 divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <li v-for="d in allDesignations" :key="d.id" class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <template v-if="editingDesigId !== d.id">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ d.name }}</span>
                                    <span v-if="!d.is_active" class="ml-2 text-xs text-gray-400">inactive</span>
                                </template>
                                <input
                                    v-else
                                    v-model="desigEditName"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                            </div>
                            <div v-if="canUpdate || canDelete" class="flex shrink-0 gap-3">
                                <template v-if="editingDesigId === d.id">
                                    <button type="button" class="text-xs font-medium text-green-600 hover:text-green-800 dark:text-green-400" @click="saveDesig(d)">Save</button>
                                    <button type="button" class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400" @click="editingDesigId = null">Cancel</button>
                                </template>
                                <template v-else>
                                    <button v-if="canUpdate" type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="startEditDesig(d)">Edit</button>
                                    <button v-if="canDelete" type="button" class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="destroyDesig(d)">Delete</button>
                                </template>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-4 flex justify-end">
                        <SecondaryButton @click="showDesigModal = false">Close</SecondaryButton>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>