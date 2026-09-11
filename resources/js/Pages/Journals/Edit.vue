<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import JournalForm from './JournalForm.vue';

interface Option {
    value: number;
    label: string;
}

interface Line {
    account_id: number | string;
    description: string;
    debit: string;
    credit: string;
}

defineProps<{
    journal: {
        id: number;
        journal_date: string;
        period_id: number;
        reference: string | null;
        description: string | null;
    };
    lines: Line[];
    periods: Option[];
    accounts: Option[];
}>();
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl">
            <PageHeader title="Edit Journal Draft" description="Update a draft journal before it is posted." />
            <JournalForm :periods="periods" :accounts="accounts" :journal="journal" :lines="lines" />
        </div>
    </AuthenticatedLayout>
</template>