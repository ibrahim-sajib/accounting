<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Company {
    id: number;
    name: string;
    legal_name: string | null;
    country_code: string | null;
    currency_code: string | null;
    tax_registration_no: string | null;
    accounting_basis: string;
    status: string;
    email: string | null;
    phone: string | null;
    city: string | null;
    branches_count: number;
    users_count: number;
    logo_url: string | null;
    created_at: string;
}

const page = usePage();
const canCreate = computed(() => page.props.auth.permissions.includes('company.create') || page.props.auth.user.is_super_admin);

const props = defineProps<{
    companies: {
        data: Company[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search?: string };
}>();

const total = computed(() => props.companies.total ?? 0);
</script>

<template>
    <AuthenticatedLayout>
        <div>
            <PageHeader title="Companies" :description="`${total} organization(s) managed in this installation`">
                <template #actions>
                    <Link
                        v-if="canCreate"
                        :href="route('companies.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <AppIcon name="plus" class="h-4 w-4" />
                        New Company
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-4">
                <SearchInput :model-value="filters?.search" placeholder="Search companies..." />
            </div>

            <div v-if="companies.data.length === 0" class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <AppIcon name="building" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No companies found.</p>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-semibold">Company</th>
                            <th class="px-4 py-3 font-semibold">Country</th>
                            <th class="px-4 py-3 font-semibold">Currency</th>
                            <th class="px-4 py-3 font-semibold">Basis</th>
                            <th class="px-4 py-3 font-semibold">Branches</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="company in companies.data" :key="company.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {{ company.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ company.name }}</div>
                                        <div class="text-xs text-gray-500">{{ company.legal_name || '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ company.country_code ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ company.currency_code ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-medium capitalize text-gray-600 dark:text-gray-300">{{ company.accounting_basis }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ company.branches_count }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="company.status" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('companies.edit', company.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    Manage
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="companies.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>