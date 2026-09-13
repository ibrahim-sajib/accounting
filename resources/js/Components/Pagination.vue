<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}>();

const currentPage = computed(() => {
    const active = props.links.find((link) => link.active);
    if (!active) {
        return 1;
    }
    const parsed = parseInt(active.label, 10);
    return Number.isNaN(parsed) ? 1 : parsed;
});

const totalPages = computed(() => {
    // Scan the numeric page labels from the right (skipping the '…' separator) to
    // get the real total instead of assuming links.length − 2.
    for (let index = props.links.length - 2; index >= 1; index--) {
        const parsed = parseInt(props.links[index].label, 10);
        if (!Number.isNaN(parsed)) {
            return parsed;
        }
    }
    return Math.max(1, props.links.length - 2);
});

const pageLinks = computed(() => props.links.slice(1, -1));

const prevLink = computed(() => props.links[0]);
const nextLink = computed(() => props.links[props.links.length - 1]);
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex items-center justify-between border-t border-gray-200 px-2 pt-3 dark:border-gray-800">
        <div class="hidden text-xs text-gray-500 sm:block dark:text-gray-400">
            Page
            <span class="font-medium text-gray-700 dark:text-gray-200">{{ currentPage }}</span>
            of <span class="font-medium text-gray-700 dark:text-gray-200">{{ totalPages }}</span>
        </div>

        <div class="flex flex-1 items-center justify-center gap-1 sm:flex-none sm:justify-end">
            <div class="flex items-center gap-1">
                <!-- Previous -->
                <span
                    v-if="!prevLink || !prevLink.url"
                    title="Previous page"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-300 dark:text-gray-600"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </span>
                <Link
                    v-else
                    :href="prevLink.url"
                    title="Previous page"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>

                <!-- Page numbers / ellipsis -->
                <template v-for="(link, index) in pageLinks" :key="index">
                    <span v-if="!link.url || link.label === '...'" class="px-1.5 text-sm text-gray-400 dark:text-gray-600">
                        {{ link.label === '...' ? '…' : '' }}
                    </span>
                    <Link
                        v-else
                        :href="link.url"
                        :class="link.active
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        class="flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-sm font-medium"
                    >
                        {{ link.label }}
                    </Link>
                </template>

                <!-- Next -->
                <span
                    v-if="!nextLink || !nextLink.url"
                    title="Next page"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-300 dark:text-gray-600"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
                <Link
                    v-else
                    :href="nextLink.url"
                    title="Next page"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>
    </nav>
</template>