<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
}>();

const colorMap: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
    inactive: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    suspended: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
    open: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
    closed: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    locked: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
    draft: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
    submitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
    pending_approval: 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300',
    approved: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300',
    posted: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
    cancelled: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    voided: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
};

const cls = computed(() => {
    const key = props.status.toLowerCase();
    return colorMap[key] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
});

const label = computed(() => props.status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()));
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="cls">
        {{ label }}
    </span>
</template>