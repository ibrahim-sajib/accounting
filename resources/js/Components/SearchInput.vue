<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = withDefaults(defineProps<{
    modelValue?: string;
    placeholder?: string;
    queryParam?: string;
}>(), {
    queryParam: 'search',
    placeholder: 'Search...',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const value = ref(props.modelValue ?? '');
let debounceTimer: ReturnType<typeof setTimeout>;

watch(() => props.modelValue, (val) => {
    value.value = val ?? '';
});

const onInput = (e: Event) => {
    value.value = (e.target as HTMLInputElement).value;
    emit('update:modelValue', value.value);

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const current = route().current() ?? '';
        if (!current) {
            return;
        }
        router.get(current, { [props.queryParam]: value.value || undefined }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    }, 350);
};
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </span>
        <input
            :value="value"
            type="search"
            :placeholder="placeholder"
            class="block w-full rounded-lg border-0 bg-white py-2 pl-10 pr-4 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 dark:bg-gray-800 dark:text-white dark:ring-gray-700"
            @input="onInput"
        />
    </div>
</template>