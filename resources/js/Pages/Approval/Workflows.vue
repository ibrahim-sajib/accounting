<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Workflow {
    id: number;
    name: string;
    module: string;
    module_label: string;
    min_amount: string;
    max_amount: string | null;
    sequence: number;
    is_active: boolean;
    approver_role_id: number | null;
    approver_role: string | null;
}

defineProps<{
    workflows: Workflow[];
    modules: { value: string; label: string }[];
    roles: { value: number; label: string }[];
}>();

const showModal = ref(false);
const editing = ref<Workflow | null>(null);

const form = useForm({
    name: '',
    module: 'sales_invoice',
    min_amount: '0',
    max_amount: '',
    approver_role_id: null as number | null,
    sequence: '1',
    is_active: true,
});

const openCreate = () => {
    editing.value = null;
    form.clearErrors();
    form.reset();
    form.is_active = true;
    showModal.value = true;
};

const openEdit = (w: Workflow) => {
    editing.value = w;
    form.clearErrors();
    form.name = w.name;
    form.module = w.module;
    form.min_amount = w.min_amount;
    form.max_amount = w.max_amount ?? '';
    form.approver_role_id = w.approver_role_id;
    form.sequence = String(w.sequence);
    form.is_active = w.is_active;
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('approval-workflows.update', editing.value.id), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('approval-workflows.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const toggleActive = (w: Workflow) => {
    router.put(route('approval-workflows.update', w.id), {
        name: w.name,
        module: w.module,
        min_amount: w.min_amount,
        max_amount: w.max_amount,
        approver_role_id: w.approver_role_id,
        sequence: w.sequence,
        is_active: !w.is_active,
    });
};

const destroy = (w: Workflow) => {
    if (confirm(`Delete workflow "${w.name}"?`)) {
        router.delete(route('approval-workflows.destroy', w.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Approval Workflows"
                description="Rules that send documents over an amount threshold through an approval chain before they can be posted."
            >
                <template #actions>
                    <button
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                        @click="openCreate"
                    >
                        <AppIcon name="plus" class="mr-1 h-4 w-4" /> New workflow
                    </button>
                </template>
            </PageHeader>

            <div class="mt-6 overflow-x-auto bg-white shadow-sm dark:bg-gray-900 sm:rounded-lg">
                <table class="min-w-[760px] lg:min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Module</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount range</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Approver</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Seq</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="w in workflows" :key="w.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ w.name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ w.module_label }}</td>
                            <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ w.min_amount }} — {{ w.max_amount ?? '∞' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ w.approver_role ?? '—' }}</td>
                            <td class="px-4 py-2 text-center text-sm text-gray-600 dark:text-gray-300">{{ w.sequence }}</td>
                            <td class="px-4 py-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="w.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ w.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openEdit(w)">
                                    <AppIcon name="pencil" class="h-4 w-4" />
                                </button>
                                <button class="ml-3 text-amber-600 hover:text-amber-800 dark:text-amber-400" @click="toggleActive(w)">
                                    <AppIcon name="unlock" class="h-4 w-4" />
                                </button>
                                <button class="ml-3 text-red-600 hover:text-red-800 dark:text-red-400" @click="destroy(w)">
                                    <AppIcon name="trash" class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="workflows.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                No workflows. Documents post directly until a rule is added.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Modal :show="showModal" @close="showModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ editing ? 'Edit workflow' : 'New workflow' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Documents that match an active rule pass through this approval step before they can be posted.
                    </p>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="wf_name" value="Name" :required="true" />
                        <TextInput id="wf_name" v-model="form.name" class="mt-1 w-full" required />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="wf_module" value="Module" :required="true" />
                        <select
                            id="wf_module"
                            v-model="form.module"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <option v-for="m in modules" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.module" />
                    </div>

                    <div>
                        <InputLabel for="wf_role" value="Approver role" />
                        <select
                            id="wf_role"
                            v-model="form.approver_role_id"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <option :value="null">Super Admin only</option>
                            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.approver_role_id" />
                    </div>

                    <div>
                        <InputLabel for="wf_min" value="Min amount" :required="true" />
                        <TextInput id="wf_min" v-model="form.min_amount" type="number" min="0" step="0.0001" class="mt-1 w-full" required />
                        <InputError class="mt-2" :message="form.errors.min_amount" />
                    </div>

                    <div>
                        <InputLabel for="wf_max" value="Max amount (blank = unlimited)" />
                        <TextInput id="wf_max" v-model="form.max_amount" type="number" min="0" step="0.0001" class="mt-1 w-full" />
                        <InputError class="mt-2" :message="form.errors.max_amount" />
                    </div>

                    <div>
                        <InputLabel for="wf_seq" value="Sequence" :required="true" />
                        <TextInput id="wf_seq" v-model="form.sequence" type="number" min="1" max="9" class="mt-1 w-full" required />
                        <InputError class="mt-2" :message="form.errors.sequence" />
                    </div>

                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Active
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing" @click="submit">
                        {{ editing ? 'Save changes' : 'Create' }}
                    </PrimaryButton>
                </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>