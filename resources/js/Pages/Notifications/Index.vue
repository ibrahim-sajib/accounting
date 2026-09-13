<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router } from '@inertiajs/vue3';
import { formatDate } from '@/utils/formatDate';

interface NotificationRow {
    id: string;
    title: string;
    body: string;
    category: string;
    read_at: string | null;
    created_at: string;
    approvals_url: string | null;
}

defineProps<{
    notifications: {
        data: NotificationRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    unread_count: number;
}>();

const markRead = (row: NotificationRow) => {
    if (row.read_at) return;
    router.post(route('notifications.read', row.id), {}, { preserveScroll: true });
};

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Notifications"
                :description="`In-app alerts from the approval engine and due-date monitoring (${unread_count} unread).`"
            >
                <template v-if="unread_count > 0" #actions>
                    <PrimaryButton @click="markAllRead">Mark all as read</PrimaryButton>
                </template>
            </PageHeader>

            <div class="mt-6 overflow-x-auto bg-white shadow-sm dark:bg-gray-900 sm:rounded-lg">
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li
                        v-for="n in notifications.data"
                        :key="n.id"
                        class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        :class="n.read_at ? 'opacity-70' : ''"
                        @click="markRead(n)"
                    >
                        <div
                            class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                            :class="n.read_at ? 'bg-gray-300 dark:bg-gray-600' : 'bg-indigo-600'"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ n.title }}</p>
                                <span class="text-xs text-gray-400">{{ formatDate(n.created_at) }}</span>
                            </div>
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ n.body }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <StatusBadge :status="n.category" />
                                <a
                                    v-if="n.approvals_url"
                                    :href="n.approvals_url"
                                    class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                    @click.stop
                                >View approvals</a>
                            </div>
                        </div>
                    </li>
                    <li v-if="notifications.data.length === 0" class="px-4 py-12 text-center text-sm text-gray-500">
                        No notifications yet. Approval events and overdue-document alerts will land here.
                    </li>
                </ul>
            </div>

            <div class="mt-4">
                <Pagination :links="notifications.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>