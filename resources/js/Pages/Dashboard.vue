<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface JournalRow {
    id: number;
    journal_no: string | null;
    journal_date: string;
    source_type: string;
    description: string | null;
}

interface Summary {
    company_name: string | null;
    accounting_basis: string | null;
}

interface Metrics {
    fiscal_year: string | null;
    active_period: string | null;
    range: { from: string; to: string };
    cash_balance: number;
    receivables: { balance: number; overdue: number; open_invoices: number };
    payables: { balance: number; overdue: number; open_bills: number };
    activity: { income: number; expense: number; net: number };
    trial_balance: { debit: number; credit: number; difference: number; balanced: boolean };
    recent_journals: JournalRow[];
}

interface Props {
    summary: Summary;
    metrics: Metrics;
}

const props = defineProps<Props>();
const page = usePage();

const currentCompany = computed(() => page.props.current_company);
const companies = computed(() => page.props.companies ?? []);

const metrics = computed(() => props.metrics);

const kpis = computed(() => [
    {
        label: 'Cash & Bank',
        value: metrics.value.cash_balance,
        icon: 'bank',
        tone: 'text-indigo-600 dark:text-indigo-300',
        bg: 'bg-indigo-50 dark:bg-indigo-950/60',
        to: route('cash-bank.index'),
    },
    {
        label: 'Receivables',
        value: metrics.value.receivables.balance,
        sub: `${metrics.value.receivables.open_invoices} open · ${formatMoney(metrics.value.receivables.overdue)} overdue`,
        icon: 'receipt',
        tone: metrics.value.receivables.overdue > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-green-600 dark:text-green-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
        to: route('receivables.index'),
    },
    {
        label: 'Payables',
        value: metrics.value.payables.balance,
        sub: `${metrics.value.payables.open_bills} open · ${formatMoney(metrics.value.payables.overdue)} overdue`,
        icon: 'bill',
        tone: metrics.value.payables.overdue > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-green-600 dark:text-green-400',
        bg: 'bg-rose-50 dark:bg-rose-950/40',
        to: route('payables.index'),
    },
    {
        label: 'Revenue',
        value: metrics.value.activity.income,
        sub: metrics.value.range.from,
        icon: 'invoice',
        tone: 'text-green-600 dark:text-green-400',
        bg: 'bg-green-50 dark:bg-green-950/40',
        to: route('statements.index', { statement: 'income' }),
    },
    {
        label: 'Expenses',
        value: metrics.value.activity.expense,
        sub: metrics.value.range.to,
        icon: 'expense',
        tone: 'text-rose-600 dark:text-rose-400',
        bg: 'bg-rose-50 dark:bg-rose-950/40',
        to: route('statements.index', { statement: 'income' }),
    },
    {
        label: 'Net Income',
        value: metrics.value.activity.net,
        sub: metrics.value.fiscal_year ?? '',
        icon: 'report',
        tone: metrics.value.activity.net >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400',
        bg: 'bg-violet-50 dark:bg-violet-950/40',
        to: route('statements.index', { statement: 'income' }),
    },
]);
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Hero -->
            <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-700 p-6 text-white shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 class="text-xl font-semibold">
                            Welcome back, {{ page.props.auth.user.name }}
                        </h1>
                        <p class="mt-1 text-indigo-100">
                            {{ currentCompany?.name ?? 'No company selected' }}
                            <span v-if="currentCompany?.currency_code" class="ms-2 rounded bg-white/10 px-2 py-0.5 text-xs">
                                {{ currentCompany.currency_code }}
                            </span>
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-lg bg-white/10 px-3 py-2">
                            <div class="text-xs text-indigo-200">Basis</div>
                            <div class="font-medium capitalize">{{ summary.accounting_basis ?? '—' }}</div>
                        </div>
                        <div class="rounded-lg bg-white/10 px-3 py-2">
                            <div class="text-xs text-indigo-200">Fiscal Year</div>
                            <div class="font-medium">{{ metrics.fiscal_year ?? '—' }}</div>
                        </div>
                        <div class="rounded-lg bg-white/10 px-3 py-2">
                            <div class="text-xs text-indigo-200">Active Period</div>
                            <div class="font-medium">{{ metrics.active_period ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!currentCompany" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                No active company. Select a company from the switcher in the top bar, or ask an administrator to create one.
            </div>

            <!-- KPI cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    :href="kpi.to"
                    class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-800"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-400">{{ kpi.label }}</div>
                            <div class="mt-1 font-mono text-xl font-semibold text-gray-900 dark:text-white">{{ formatMoney(kpi.value) }}</div>
                            <div v-if="kpi.sub" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ kpi.sub }}</div>
                        </div>
                        <div :class="`flex h-10 w-10 items-center justify-center rounded-lg ${kpi.bg} ${kpi.tone}`">
                            <AppIcon :name="kpi.icon" class="h-5 w-5" />
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Ledger health + open items -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Trial Balance</h2>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="metrics.trial_balance.balanced ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'"
                        >
                            {{ metrics.trial_balance.balanced ? 'In balance' : 'Mismatch' }}
                        </span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                            <div class="text-xs uppercase tracking-wide text-gray-400">Debit</div>
                            <div class="mt-0.5 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(metrics.trial_balance.debit) }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                            <div class="text-xs uppercase tracking-wide text-gray-400">Credit</div>
                            <div class="mt-0.5 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(metrics.trial_balance.credit) }}</div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ metrics.range.from }} → {{ metrics.range.to }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Receivables & Payables</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Open invoices</span>
                            <span class="font-mono font-medium text-gray-900 dark:text-white">{{ metrics.receivables.open_invoices }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Open bills</span>
                            <span class="font-mono font-medium text-gray-900 dark:text-white">{{ metrics.payables.open_bills }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Overdue receivables</span>
                            <span class="font-mono font-medium text-amber-600 dark:text-amber-400">{{ formatMoney(metrics.receivables.overdue) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Overdue payables</span>
                            <span class="font-mono font-medium text-amber-600 dark:text-amber-400">{{ formatMoney(metrics.payables.overdue) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Reporting</h2>
                    <div class="mt-3 space-y-2">
                        <Link :href="route('reports.index')" class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-700 dark:bg-gray-800/50 dark:text-gray-300 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-300">
                            <AppIcon name="report" class="h-4 w-4" /> General Ledger & Trial Balance
                        </Link>
                        <Link :href="route('statements.index')" class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-700 dark:bg-gray-800/50 dark:text-gray-300 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-300">
                            <AppIcon name="statement" class="h-4 w-4" /> Financial Statements
                        </Link>
                        <Link :href="route('budgets.index')" class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-700 dark:bg-gray-800/50 dark:text-gray-300 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-300">
                            <AppIcon name="budget" class="h-4 w-4" /> Budget vs Actual
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Recent journals -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Recent Journals</h2>
                    <Link
                        :href="route('journals.index')"
                        class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        View all
                    </Link>
                </div>
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Journal No</th>
                            <th class="px-5 py-3 font-semibold">Source</th>
                            <th class="px-5 py-3 font-semibold">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="j in metrics.recent_journals" :key="j.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-5 py-2.5 font-mono text-gray-500 dark:text-gray-400">{{ j.journal_date }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                <Link :href="route('journals.show', j.id)">{{ j.journal_no ?? j.source_type }}</Link>
                            </td>
                            <td class="px-5 py-2.5 text-xs uppercase tracking-wide text-gray-400">{{ j.source_type }}</td>
                            <td class="px-5 py-2.5 text-gray-600 dark:text-gray-300">{{ j.description ?? '—' }}</td>
                        </tr>
                        <tr v-if="metrics.recent_journals.length === 0">
                            <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No posted journals yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Multi-company -->
            <div v-if="companies.length > 1" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Your Companies</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="company in companies"
                        :key="company.id"
                        class="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3 dark:border-gray-800"
                    >
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ company.name }}</div>
                            <div class="text-xs text-gray-500">{{ company.currency_code ?? '—' }} · {{ company.status }}</div>
                        </div>
                        <span
                            v-if="company.id === currentCompany?.id"
                            class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300"
                        >
                            Active
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>