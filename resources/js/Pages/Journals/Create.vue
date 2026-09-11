<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import JournalForm from './JournalForm.vue';

interface Option {
    value: number;
    label: string;
}

defineProps<{
    periods: Option[];
    accounts: Option[];
    today: string;
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl">
            <PageHeader title="New Journal Entry" description="Record a manual journal entry. Save it as a draft, review, then post." />
            <JournalForm :periods="periods" :accounts="accounts" :today="today" />
        </div>
    </AuthenticatedLayout>
</template>