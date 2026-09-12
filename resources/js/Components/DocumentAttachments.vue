<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { formatDate } from '@/utils/formatDate';

interface AttachmentRow {
    id: number;
    original_name: string;
    mime_type: string;
    file_size: number;
    created_at: string;
    uploaded_by: string;
}

const props = defineProps<{
    attachments: AttachmentRow[];
    storeUrl: string;
    permission: string;
}>();

const page = usePage();
const canUpload = computed(() => props.permission && (page.props.auth.permissions.includes(props.permission) || page.props.auth.user.is_super_admin));

const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);

const humanSize = (bytes: number) => {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const isImage = (mime: string) => mime.startsWith('image/');

const pickFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    uploading.value = true;
    router.post(props.storeUrl, { file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (fileInput.value) fileInput.value.value = '';
            uploading.value = false;
        },
        onError: () => {
            uploading.value = false;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const remove = (row: AttachmentRow) => {
    if (confirm(`Remove "${row.original_name}" from this document? The file will be deleted.`)) {
        router.delete(route('attachments.destroy', row.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Documents</h2>
            <label
                v-if="canUpload"
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" /></svg>
                {{ uploading ? 'Uploading…' : 'Attach file' }}
                <input ref="fileInput" type="file" class="hidden" @change="pickFile" />
            </label>
        </div>

        <p v-if="attachments.length === 0" class="mt-3 text-sm text-gray-400">
            No documents attached yet.
        </p>

        <ul v-else class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
            <li v-for="a in attachments" :key="a.id" class="flex items-center gap-3 py-2.5 text-sm">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                    :class="isImage(a.mime_type) ? 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400'"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path v-if="isImage(a.mime_type)" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4z" />
                        <path v-else d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M9 15h6M9 11h6" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <a
                        :href="route('attachments.preview', a.id)"
                        target="_blank"
                        rel="noopener"
                        class="truncate font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                        :title="a.original_name"
                    >{{ a.original_name }}</a>
                    <div class="text-xs text-gray-400">
                        {{ humanSize(a.file_size) }} · {{ a.uploaded_by }} · {{ formatDate(a.created_at) }}
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <a
                        :href="route('attachments.download', a.id)"
                        class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                        title="Download"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" /></svg>
                    </a>
                    <button
                        v-if="canUpload"
                        type="button"
                        class="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                        title="Remove"
                        @click="remove(a)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6z" /></svg>
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>