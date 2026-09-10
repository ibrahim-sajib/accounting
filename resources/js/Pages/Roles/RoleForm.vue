<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Permission {
    id: number;
    name: string;
    slug: string;
    action: string;
}

interface PermissionGroup {
    module: string;
    permissions: Permission[];
}

const props = defineProps<{
    role?: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        is_system: boolean;
        company_id: number | null;
    };
    selectedPermissions?: number[];
    permissionGroups: PermissionGroup[];
}>();

const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    permissions: (props.selectedPermissions ?? []) as number[],
});

const expandedModules = ref<string[]>(props.permissionGroups.map((g) => g.module));

const allPermissionIds = computed(() => props.permissionGroups.flatMap((g) => g.permissions.map((p) => p.id)));

const allSelected = computed(
    () => allPermissionIds.value.length > 0 && allPermissionIds.value.every((id) => form.permissions.includes(id)),
);

const moduleSelected = (group: PermissionGroup) => {
    const ids = group.permissions.map((p) => p.id);
    return ids.every((id) => form.permissions.includes(id));
};

const toggleModule = (group: PermissionGroup) => {
    const ids = group.permissions.map((p) => p.id);
    if (moduleSelected(group)) {
        form.permissions = form.permissions.filter((id) => !ids.includes(id));
    } else {
        form.permissions = form.permissions.concat(ids.filter((id) => !form.permissions.includes(id)));
    }
};

const togglePermission = (id: number) => {
    if (form.permissions.includes(id)) {
        form.permissions = form.permissions.filter((p) => p !== id);
    } else {
        form.permissions.push(id);
    }
};

const toggleAll = () => {
    form.permissions = allSelected.value ? [] : [...allPermissionIds.value];
};

const isExpanded = (module: string) => expandedModules.value.includes(module);

const toggleExpanded = (module: string) => {
    expandedModules.value = isExpanded(module)
        ? expandedModules.value.filter((m) => m !== module)
        : [...expandedModules.value, module];
};

const submit = () => {
    if (props.role) {
        form.put(route('roles.update', props.role.id));
    } else {
        form.post(route('roles.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Role Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="description" value="Description" />
                    <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Module Permissions</h3>
                <button
                    type="button"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-700 dark:text-gray-300"
                    @click="toggleAll"
                >
                    {{ allSelected ? 'Clear all' : 'Select all' }}
                </button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="group in permissionGroups"
                    :key="group.module"
                    class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/60"
                        @click="toggleExpanded(group.module)"
                    >
                        <div class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                :checked="moduleSelected(group)"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                                @click.stop="toggleModule(group)"
                            />
                            <span class="text-sm font-semibold capitalize text-gray-800 dark:text-white">{{ group.module.replace(/_/g, ' ') }}</span>
                        </div>
                        <svg class="h-4 w-4 text-gray-400 transition-transform" :class="isExpanded(group.module) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-if="isExpanded(group.module)" class="grid grid-cols-2 gap-1 border-t border-gray-100 px-4 py-3 sm:grid-cols-3 lg:grid-cols-4 dark:border-gray-800">
                        <label
                            v-for="permission in group.permissions"
                            :key="permission.id"
                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            <input
                                type="checkbox"
                                :checked="form.permissions.includes(permission.id)"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                                @change="togglePermission(permission.id)"
                            />
                            {{ permission.name }}
                        </label>
                    </div>
                </div>
            </div>

            <InputError :message="form.errors.permissions" class="mt-2" />
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('roles.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ role ? 'Save Changes' : 'Create Role' }}
            </PrimaryButton>
        </div>
    </form>
</template>