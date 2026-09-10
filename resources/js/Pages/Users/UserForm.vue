<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.user.is_super_admin);

interface Option { value: number; label: string; }

const props = defineProps<{
    user?: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        status: string;
        company_id: number | null;
    };
    companies: { id: number; name: string }[] | null;
    roles: Option[];
    statusOptions: { value: string; label: string }[];
    userAccess?: { company_id: number; branch_id: number | null; is_default: boolean }[];
    userRoles?: number[];
    branches?: Option[];
}>();

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    password: '',
    password_confirmation: '',
    status: props.user?.status ?? 'active',
    company_id: props.user?.company_id ?? page.props.current_company?.id ?? null,
    roles: (props.userRoles ?? []) as number[],
    company_access: (props.userAccess ?? []).map((a) => ({ ...a })),
});

const showCompanyAccess = ref(false);

const toggleRole = (roleId: number) => {
    const index = form.roles.indexOf(roleId);
    if (index === -1) {
        form.roles.push(roleId);
    } else {
        form.roles.splice(index, 1);
    }
};

const addAccess = () => {
    form.company_access.push({
        company_id: form.company_id ?? 0,
        branch_id: null,
        is_default: form.company_access.length === 0,
    });
};

const removeAccess = (index: number) => {
    form.company_access.splice(index, 1);
};

const submit = () => {
    if (props.user) {
        form.put(route('users.update', props.user.id));
    } else {
        form.post(route('users.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">User Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Full Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="email" value="Email" required />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="phone" value="Phone" />
                    <TextInput id="phone" v-model="form.phone" type="tel" class="mt-1 block w-full" />
                    <InputError :message="form.errors.phone" class="mt-1" />
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
                <div>
                    <InputLabel for="password" :value="user ? 'New Password (optional)' : 'Password'" :required="!user" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" :required="!user" autocomplete="new-password" />
                    <InputError :message="form.errors.password" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Confirm Password" :required="!user" />
                    <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" :required="!user" autocomplete="new-password" />
                    <InputError :message="form.errors.password_confirmation" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Roles</h3>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="role in roles"
                    :key="role.value"
                    type="button"
                    :class="form.roles.includes(role.value)
                        ? 'border-indigo-600 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                        : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'"
                    class="rounded-lg border px-3 py-1.5 text-sm font-medium hover:border-indigo-400"
                    @click="toggleRole(role.value)"
                >
                    {{ role.label }}
                </button>
            </div>
            <InputError :message="form.errors.roles" class="mt-1" />
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Company Access</h3>
                <button
                    v-if="isSuperAdmin"
                    type="button"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                    @click="showCompanyAccess = !showCompanyAccess"
                >
                    {{ showCompanyAccess ? 'Hide' : 'Configure' }}
                </button>
            </div>

            <div v-if="isSuperAdmin && showCompanyAccess" class="mt-4 space-y-3">
                <div v-for="(access, index) in form.company_access" :key="index" class="flex flex-wrap items-end gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                    <div class="min-w-[180px] flex-1">
                        <InputLabel :for="`company_${index}`" value="Company" />
                        <select
                            :id="`company_${index}`"
                            v-model="access.company_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option v-for="company in companies" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-[160px] flex-1">
                        <InputLabel :for="`branch_${index}`" value="Branch (optional)" />
                        <select
                            :id="`branch_${index}`"
                            v-model="access.branch_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option :value="null">All branches</option>
                            <option v-for="branch in branches ?? []" :key="branch.value" :value="branch.value">
                                {{ branch.label }}
                            </option>
                        </select>
                    </div>
                    <label class="mb-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input
                            type="checkbox"
                            v-model="access.is_default"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                            @change="access.is_default ? form.company_access.forEach((a, i) => i !== index && (a.is_default = false)) : null"
                        />
                        Default
                    </label>
                    <button
                        type="button"
                        class="mb-1 rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600"
                        title="Remove access"
                        @click="removeAccess(index)"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <button
                    type="button"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                    @click="addAccess"
                >
                    + Add another company
                </button>
            </div>

            <p v-else class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                The user will be granted access to the primary company of the account.
            </p>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('users.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ user ? 'Save Changes' : 'Create User' }}
            </PrimaryButton>
        </div>
    </form>
</template>