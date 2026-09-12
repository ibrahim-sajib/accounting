<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface Entry {
    id: number;
    journal_id: number;
    journal_no: string | null;
    journal_date: string;
    source_type: string;
    reference: string | null;
    description: string | null;
    account_id: number;
    account_code: string;
    account_name: string;
    debit: number;
    credit: number;
    running_balance: number;
}

interface SummaryRow {
    account_id: number;
    account_code: string;
    account_name: string;
    debit: number;
    credit: number;
    net: number;
    closing_balance: number;
}

interface TbRow {
    account_id: number;
    code: string;
    name: string;
    type: string;
    type_label: string;
    debit: number;
    credit: number;
    balance: number;
}

const props = defineProps<{
    report: 'general-ledger' | 'trial-balance';
    filters: {
        fiscalYears: { value: number; label: string }[];
        periods: { value: number; label: string; fiscal_year_id: number }[];
        selectedFiscalYearId: number | null;
        selectedPeriodId: number | null;
        from: string;
        to: string;
    };
    accounts?: { value: number; label: string }[];
    entries?: Entry[];
    summary?: SummaryRow[];
    totals?: { debit: number; credit: number };
    rows?: TbRow[];
    balanced?: boolean;
}>();

const visiblePeriods = computed(() =>
    props.filters.periods.filter((p) => p.fiscal_year_id === props.filters.selectedFiscalYearId)
);

const go = (extra: Record<string, unknown> = {}) => {
    router.get(route('reports.index'), {
        report: props.report,
        fiscal_year_id: props.filters.selectedFiscalYearId ?? undefined,
        period_id: props.filters.selectedPeriodId ?? undefined,
        ...extra,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['report', 'filters', 'accounts', 'entries', 'summary', 'totals', 'rows', 'balanced'],
    });
};

const onFiscalYear = (e: Event) => {
    const v = (e.target as HTMLSelectElement).value;
    go({ fiscal_year_id: v || undefined, period_id: undefined, account_id: undefined });
};

const onPeriod = (v: string) => {
    go({ period_id: v || undefined, account_id: undefined });
};

const onAccount = (e: Event) => {
    const v = (e.target as HTMLSelectElement).value;
    go({ account_id: v || undefined });
};

const onReport = (report: string) => {
    router.get(route('reports.index'), {
        report,
        fiscal_year_id: props.filters.selectedFiscalYearId ?? undefined,
        period_id: props.filters.selectedPeriodId ?? undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['report', 'filters', 'accounts', 'entries', 'summary', 'totals', 'rows', 'balanced'],
    });
};

const diff = computed(() => (props.totals?.debit ?? 0) - (props.totals?.credit ?? 0));

interface LedgerRowHeader { kind: 'header'; account_code: string; account_name: string; }
interface LedgerRowEntry { kind: 'entry'; entry: Entry; }
type LedgerRow = LedgerRowHeader | LedgerRowEntry;

const ledgerRows = computed<LedgerRow[]>(() => {
    const rows: LedgerRow[] = [];
    let lastAccount: number | null = null;

    for (const entry of props.entries ?? []) {
        if (entry.account_id !== lastAccount) {
            rows.push({ kind: 'header', account_code: entry.account_code, account_name: entry.account_name });
            lastAccount = entry.account_id;
        }
        rows.push({ kind: 'entry', entry });
    }

    return rows;
});
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="Reports"
            description="Query layers over posted journal lines — general ledger and trial balance."
        />

        <div class="mx-auto max-w-6xl">
            <div class="mb-4 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="report === 'general-ledger' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'"
                    @click="onReport('general-ledger')"
                >
                    General Ledger
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="report === 'trial-balance' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'"
                    @click="onReport('trial-balance')"
                >
                    Trial Balance
                </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <InputLabel for="report_fy" value="Fiscal Year" />
                    <select
                        id="report_fy"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters.selectedFiscalYearId ?? ''"
                        @change="onFiscalYear"
                    >
                        <option v-for="f in filters.fiscalYears" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                </div>

                <div>
                    <InputLabel for="report_period" value="Period" />
                    <select
                        id="report_period"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters.selectedPeriodId ?? ''"
                        @change="(e) => onPeriod((e.target as HTMLSelectElement).value)"
                    >
                        <option value="">Whole fiscal year</option>
                        <option v-for="p in visiblePeriods" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>

                <div v-if="report === 'general-ledger'">
                    <InputLabel for="report_account" value="Account" />
                    <select
                        id="report_account"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        @change="onAccount"
                    >
                        <option value="">All accounts</option>
                        <option v-for="a in accounts ?? []" :key="a.value" :value="a.value">{{ a.label }}</option>
                    </select>
                </div>

                <div class="flex items-end pb-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Range: <span class="font-mono text-gray-700 dark:text-gray-300">{{ filters.from }}</span> → <span class="font-mono text-gray-700 dark:text-gray-300">{{ filters.to }}</span>
                    </p>
                </div>
            </div>

            <!-- ── Trial Balance ── -->
            <div v-if="report === 'trial-balance'">
                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total Debit</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ formatMoney(totals?.debit ?? 0) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total Credit</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ formatMoney(totals?.credit ?? 0) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Difference</div>
                        <div class="mt-1 font-mono text-lg font-semibold" :class="balanced ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ formatMoney(diff) }}
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-semibold">Code</th>
                                <th class="px-4 py-3 font-semibold">Account</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                                <th class="px-4 py-3 text-right font-semibold">Debit</th>
                                <th class="px-4 py-3 text-right font-semibold">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="row in rows ?? []" :key="row.account_id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2.5 font-mono text-gray-500 dark:text-gray-400">{{ row.code }}</td>
                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ row.name }}</td>
                                <td class="px-4 py-2.5 text-xs text-gray-400">{{ row.type_label }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ row.debit ? formatMoney(row.debit) : '—' }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ row.credit ? formatMoney(row.credit) : '—' }}</td>
                            </tr>
                            <tr v-if="(rows ?? []).length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">No posted journal lines in this range.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <td colspan="3" class="px-4 py-3 font-semibold">Totals</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(totals?.debit ?? 0) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ formatMoney(totals?.credit ?? 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- ── General Ledger ── -->
            <div v-else>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ledger Entries</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Date</th>
                                    <th class="px-4 py-3 font-semibold">Journal</th>
                                    <th class="px-4 py-3 font-semibold">Account</th>
                                    <th class="px-4 py-3 font-semibold">Description</th>
                                    <th class="px-4 py-3 text-right font-semibold">Debit</th>
                                    <th class="px-4 py-3 text-right font-semibold">Credit</th>
                                    <th class="px-4 py-3 text-right font-semibold">Balance</th>
                                </tr>
                            </thead>
                            <tbody v-if="ledgerRows.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template v-for="(row, idx) in ledgerRows" :key="idx">
                                    <tr v-if="row.kind === 'header'" class="bg-gray-50/70 dark:bg-gray-800/40">
                                        <td colspan="7" class="px-4 py-2">
                                            <span class="font-mono text-xs text-gray-400">{{ row.account_code }}</span>
                                            <span class="ml-1 text-sm font-semibold text-gray-900 dark:text-white">{{ row.account_name }}</span>
                                        </td>
                                    </tr>
                                    <tr v-else class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-2.5 font-mono text-gray-500 dark:text-gray-400">{{ row.entry.journal_date }}</td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ row.entry.journal_no ?? row.entry.source_type }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="font-mono text-xs text-gray-400">{{ row.entry.account_code }}</span>
                                            <span class="ml-1 text-gray-900 dark:text-white">{{ row.entry.account_name }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ row.entry.description ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ row.entry.debit ? formatMoney(row.entry.debit) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ row.entry.credit ? formatMoney(row.entry.credit) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(row.entry.running_balance) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                            <tbody v-else>
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">No posted journal lines in this range.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Per-Account Movement</h2>
                    </div>
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-semibold">Account</th>
                                <th class="px-4 py-3 text-right font-semibold">Total Debit</th>
                                <th class="px-4 py-3 text-right font-semibold">Total Credit</th>
                                <th class="px-4 py-3 text-right font-semibold">Net</th>
                                <th class="px-4 py-3 text-right font-semibold">Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="row in summary ?? []" :key="row.account_id">
                                <td class="px-4 py-2.5">
                                    <span class="font-mono text-xs text-gray-400">{{ row.account_code }}</span>
                                    <span class="ml-1 font-medium text-gray-900 dark:text-white">{{ row.account_name }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.debit) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ formatMoney(row.credit) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono" :class="row.net >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'">{{ formatMoney(row.net) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-medium text-gray-900 dark:text-white">{{ formatMoney(row.closing_balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>