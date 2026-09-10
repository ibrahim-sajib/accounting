<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    branch?: {
        id: number;
        code: string;
        name: string;
        address: string | null;
        city: string | null;
        state: string | null;
        zip_code: string | null;
        country_code: string | null;
        email: string | null;
        phone: string | null;
        manager_user_id: number | null;
        status: string;
    };
    company: { id: number; name: string } | null;
    managers: { value: number; label: string }[];
    statusOptions: { value: string; label: string }[];
}>();

const form = useForm({
    code: props.branch?.code ?? '',
    name: props.branch?.name ?? '',
    address: props.branch?.address ?? '',
    city: props.branch?.city ?? '',
    state: props.branch?.state ?? '',
    zip_code: props.branch?.zip_code ?? '',
    country_code: props.branch?.country_code ?? 'BD',
    email: props.branch?.email ?? '',
    phone: props.branch?.phone ?? '',
    manager_user_id: props.branch?.manager_user_id ?? '',
    status: props.branch?.status ?? 'active',
});

const submit = () => {
    if (props.branch) {
        form.put(route('branches.update', props.branch.id));
    } else {
        form.post(route('branches.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Branch Details</h3>
            <div v-if="company" class="mb-4 rounded-lg bg-gray-50 px-4 py-2 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                Company: <span class="font-medium text-gray-700 dark:text-gray-200">{{ company.name }}</span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="code" value="Branch Code" required />
                    <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" placeholder="HQ, DHK-01" required />
                    <InputError :message="form.errors.code" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="name" value="Branch Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="manager_user_id" value="Branch Manager" />
                    <select id="manager_user_id" v-model="form.manager_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— None —</option>
                        <option v-for="manager in managers" :key="manager.value" :value="manager.value">
                            {{ manager.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.manager_user_id" class="mt-1" />
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
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Address & Contact</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <div>
                    <InputLabel for="country_code" value="Country Code" />
                    <TextInput id="country_code" v-model="form.country_code" type="text" class="mt-1 block w-full" maxlength="2" />
                    <InputError :message="form.errors.country_code" class="mt-1" />
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
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('branches.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ branch ? 'Save Changes' : 'Create Branch' }}
            </PrimaryButton>
        </div>
    </form>
</template>