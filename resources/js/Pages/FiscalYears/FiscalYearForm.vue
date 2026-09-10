<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    fiscalYear?: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        is_active: boolean;
    };
}>();

const form = useForm({
    name: props.fiscalYear?.name ?? '',
    start_date: props.fiscalYear?.start_date ?? '',
    end_date: props.fiscalYear?.end_date ?? '',
    is_active: props.fiscalYear?.is_active ?? true,
});

const submit = () => {
    if (props.fiscalYear) {
        form.put(route('fiscal-years.update', props.fiscalYear.id));
    } else {
        form.post(route('fiscal-years.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Fiscal Year Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Fiscal Year Name" required />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="2026-2027" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="start_date" value="Start Date" required />
                    <TextInput id="start_date" v-model="form.start_date" type="date" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.start_date" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="end_date" value="End Date" required />
                    <TextInput id="end_date" v-model="form.end_date" type="date" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.end_date" class="mt-1" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                        />
                        Set as active fiscal year
                    </label>
                </div>
            </div>
            <p class="mt-3 rounded-lg bg-indigo-50 px-4 py-2 text-xs text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                Monthly accounting periods will be generated automatically from the start and end dates.
            </p>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Link :href="route('fiscal-years.index')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                Cancel
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ fiscalYear ? 'Save Changes' : 'Create Fiscal Year' }}
            </PrimaryButton>
        </div>
    </form>
</template>