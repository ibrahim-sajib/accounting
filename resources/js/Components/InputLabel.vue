<script setup lang="ts">
defineProps<{
    value?: string;
    required?: boolean;
}>();
</script>

<template>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
        <span v-if="required" class="ms-0.5 text-red-500" aria-hidden="true" title="Required">*</span>
    </label>
</template>