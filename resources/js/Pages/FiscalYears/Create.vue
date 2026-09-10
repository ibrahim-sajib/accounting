<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FiscalYearForm from './FiscalYearForm.vue';
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Create Fiscal Year" description="Define a new accounting year with auto-generated monthly periods." />
            <FiscalYearForm />
        </div>
    </AuthenticatedLayout>
</template>