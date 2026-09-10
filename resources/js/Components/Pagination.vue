<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex items-center justify-between border-t border-gray-200 px-2 pt-3 dark:border-gray-800">
        <div class="text-xs text-gray-500 dark:text-gray-400">
            Page
            <span class="font-medium text-gray-700 dark:text-gray-200">{{ links.find((l) => l.active)?.label ?? '1' }}</span>
            of <span class="font-medium text-gray-700 dark:text-gray-200">{{ links.length - 2 }}</span>
        </div>

        <div class="flex items-center gap-1">
            <template v-for="(link, index) in links" :key="index">
                <span v-if="link.url === null" class="px-2 text-gray-400 dark:text-gray-600">
                    {{ link.label }}
                </span>
                <Link
                    v-else
                    :href="link.url"
                    :class="link.active
                        ? 'bg-indigo-600 text-white'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium"
                >
                    {{ link.label }}
                </Link>
            </template>
        </div>
    </nav>
</template>