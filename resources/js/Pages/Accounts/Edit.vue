<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

interface Account {
    id: number;
    code: string;
    name: string;
    name_bn: string | null;
    type: string;
    parent_id: number | null;
    is_active: boolean;
}

interface ParentOption {
    id: number;
    code: string;
    name: string;
    level: number;
}

const props = defineProps<{
    account: Account;
    parentOptions: ParentOption[];
    typeOptions: { value: string; label: string }[];
}>();

const form = useForm({
    code: props.account.code,
    name: props.account.name,
    name_bn: props.account.name_bn ?? '',
    type: props.account.type,
    parent_id: props.account.parent_id,
    is_active: props.account.is_active,
});

const submit = () => {
    form.put(route('accounts.update', props.account.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl">
            <PageHeader title="Edit Account" :description="`Editing ${account.code} — ${account.name}`">
                <template #actions>
                    <Link
                        :href="route('accounts.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </Link>
                </template>
            </PageHeader>

            <form @submit.prevent="submit" class="mt-8 space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="code" value="Account Code" required />
                        <TextInput id="code" v-model="form.code" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.code" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="name" value="Account Name" required />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="name_bn" value="Bengali Name" />
                        <TextInput id="name_bn" v-model="form.name_bn" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="type" value="Account Type" required />
                        <select
                            id="type"
                            v-model="form.type"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        >
                            <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="parent_id" value="Parent Account" />
                        <select
                            id="parent_id"
                            v-model="form.parent_id"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option :value="null">— Root Account —</option>
                            <option v-for="p in parentOptions" :key="p.id" :value="p.id">
                                {{ '·'.repeat(p.level) }} {{ p.code }} {{ p.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.parent_id" class="mt-2" />
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <InputLabel for="is_active" value="Active" class="!mt-0" />
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <AppIcon name="check" class="h-4 w-4" />
                        Update Account
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>