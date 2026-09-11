<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    warehouse?: {
        id: number;
        code: string;
        name: string;
        address: string | null;
        branch_id: number | null;
        manager_user_id: number | null;
        is_active: boolean;
    };
    branches: { value: number; label: string }[];
    managers: { value: number; label: string }[];
}>();

const form = useForm({
    code: props.warehouse?.code ?? '',
    name: props.warehouse?.name ?? '',
    address: props.warehouse?.address ?? '',
    branch_id: props.warehouse?.branch_id ?? '',
    manager_user_id: props.warehouse?.manager_user_id ?? '',
    is_active: props.warehouse?.is_active ?? true,
});

const submit = () => {
    if (props.warehouse) {
        form.put(route('warehouses.update', props.warehouse.id));
    } else {
        form.post(route('warehouses.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Warehouse Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="code" value="Warehouse Code" required />
                    <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" placeholder="WH-01" required />
                    <InputError :message="form.errors.code" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="name" value="Warehouse Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel for="address" value="Address" />
                    <TextInput id="address" v-model="form.address" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.address" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="branch_id" value="Branch" />
                    <select id="branch_id" v-model="form.branch_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— Company-wide —</option>
                        <option v-for="branch in branches" :key="branch.value" :value="branch.value">
                            {{ branch.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.branch_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="manager_user_id" value="Manager" />
                    <select id="manager_user_id" v-model="form.manager_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="manager in managers" :key="manager.value" :value="manager.value">
                            {{ manager.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.manager_user_id" class="mt-1" />
                </div>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800" />
                Active warehouse
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('warehouses.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ warehouse ? 'Save Changes' : 'Create Warehouse' }}
            </PrimaryButton>
        </div>
    </form>
</template>