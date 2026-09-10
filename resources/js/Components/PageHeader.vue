<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    description?: string;
    showBack?: boolean;
}>();
</script>

<template>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <Link
                v-if="showBack"
                :href="route('dashboard')"
                class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Dashboard
            </Link>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-3">
            <slot name="actions" />
        </div>
    </div>
</template>