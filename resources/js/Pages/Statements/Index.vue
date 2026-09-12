<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

interface Filters {
    fiscalYears: { value: number; label: string }[];
    periods: { value: number; label: string; fiscal_year_id: number }[];
    selectedFiscalYearId: number | null;
    selectedPeriodId: number | null;
    from: string;
    to: string;
}

interface StatementRow {
    level: number;
    code: string;
    name: string;
    current: number;
    ytd: number;
    is_group: boolean;
    is_total: boolean;
    type?: string;
    synthetic?: boolean;
}

interface EquityRow {
    code: string;
    name: string;
    opening: number;
    movement: number;
    closing: number;
    synthetic?: boolean;
}

interface CashFlowRow {
    key: string;
    name: string;
    amount: number;
}

const props = defineProps<{
    statement: 'income' | 'balance-sheet' | 'cash-flow' | 'equity';
    filters: Filters;
    rows?: StatementRow[] | CashFlowRow[] | EquityRow[];
    totals?: {
        income?: number;
        expense?: number;
        net_income?: number;
        income_ytd?: number;
        expense_ytd?: number;
        net_income_ytd?: number;
        assets?: number;
        liabilities?: number;
        equity?: number;
        liabilities_equity?: number;
        difference?: number;
        opening?: number;
        movement?: number;
        closing?: number;
    };
    sections?: {
        assets: StatementRow[];
        liabilities: StatementRow[];
        equity: StatementRow[];
    };
    opening?: number;
    closing?: number;
    net_change?: number;
    reconciled?: boolean;
}>();

const visiblePeriods = computed(() =>
    props.filters.periods.filter((p) => p.fiscal_year_id === props.filters.selectedFiscalYearId)
);

const only = ['statement', 'filters', 'rows', 'totals', 'sections', 'opening', 'closing', 'net_change', 'reconciled'];

const go = (extra: Record<string, unknown> = {}) => {
    router.get(route('statements.index'), {
        statement: props.statement,
        fiscal_year_id: props.filters.selectedFiscalYearId ?? undefined,
        period_id: props.filters.selectedPeriodId ?? undefined,
        ...extra,
    }, { preserveState: true, preserveScroll: true, only });
};

const onFiscalYear = (e: Event) => {
    const v = (e.target as HTMLSelectElement).value;
    go({ fiscal_year_id: v || undefined, period_id: undefined });
};

const onPeriod = (e: Event) => {
    const v = (e.target as HTMLSelectElement).value;
    go({ period_id: v || undefined });
};

const onStatement = (statement: string) => {
    router.get(route('statements.index'), {
        statement,
        fiscal_year_id: props.filters.selectedFiscalYearId ?? undefined,
        period_id: props.filters.selectedPeriodId ?? undefined,
    }, { preserveState: true, preserveScroll: true, only });
};

const tabs = [
    { key: 'income', label: 'Income Statement' },
    { key: 'balance-sheet', label: 'Balance Sheet' },
    { key: 'cash-flow', label: 'Cash Flow' },
    { key: 'equity', label: 'Statement of Equity' },
];

const money = (v: number | undefined, dash = false) => (v === undefined || v === null ? (dash ? '—' : formatMoney(0)) : formatMoney(v));

const indent = (row: StatementRow) => ({ paddingLeft: `${row.level * 1.25 + 0.5}rem` });

const bsSections: { key: 'assets' | 'liabilities' | 'equity'; title: string }[] = [
    { key: 'assets', title: 'Assets' },
    { key: 'liabilities', title: 'Liabilities' },
    { key: 'equity', title: 'Equity' },
];
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader
            title="Financial Statements"
            description="Income statement, balance sheet, cash flow and equity — derived directly from posted journal lines."
        />

        <div class="mx-auto max-w-6xl">
            <div class="mb-4 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="statement === t.key ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'"
                    @click="onStatement(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <InputLabel for="st_fy" value="Fiscal Year" />
                    <select
                        id="st_fy"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters.selectedFiscalYearId ?? ''"
                        @change="onFiscalYear"
                    >
                        <option v-for="f in filters.fiscalYears" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                </div>

                <div>
                    <InputLabel for="st_period" value="Period" />
                    <select
                        id="st_period"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        :value="filters.selectedPeriodId ?? ''"
                        @change="onPeriod"
                    >
                        <option value="">Whole fiscal year</option>
                        <option v-for="p in visiblePeriods" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>

                <div class="flex items-end pb-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Range: <span class="font-mono text-gray-700 dark:text-gray-300">{{ filters.from }}</span> → <span class="font-mono text-gray-700 dark:text-gray-300">{{ filters.to }}</span>
                    </p>
                </div>
            </div>

            <!-- ── Income Statement ── -->
            <div v-if="statement === 'income'">
                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total Income</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ money(totals?.income) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total Expenses</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ money(totals?.expense) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Net Income</div>
                        <div class="mt-1 font-mono text-lg font-semibold" :class="(totals?.net_income ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ money(totals?.net_income) }}
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-semibold">Account</th>
                                <th class="px-4 py-3 text-right font-semibold">Current</th>
                                <th class="px-4 py-3 text-right font-semibold">Year-to-date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template v-for="(row, idx) in (rows as StatementRow[]) ?? []" :key="idx">
                                <tr v-if="row.is_group" class="bg-gray-50/70 dark:bg-gray-800/40">
                                    <td class="px-4 py-2.5 font-semibold" :style="row.is_total ? undefined : indent(row)">
                                        <span v-if="!row.is_total && row.code" class="mr-1 font-mono text-xs text-gray-400">{{ row.code }}</span>{{ row.name }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono font-medium text-gray-900 dark:text-white">{{ money(row.current) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-medium text-gray-900 dark:text-white">{{ money(row.ytd) }}</td>
                                </tr>
                                <tr v-else class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-2" :style="indent(row)">
                                        <span class="mr-1 font-mono text-xs text-gray-400">{{ row.code }}</span>{{ row.name }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-gray-900 dark:text-white">{{ money(row.current) }}</td>
                                    <td class="px-4 py-2 text-right font-mono text-gray-900 dark:text-white">{{ money(row.ytd) }}</td>
                                </tr>
                            </template>
                            <tr v-if="(rows ?? []).length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-400">No income/expense activity in this range.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <td class="px-4 py-3 font-semibold">Net income (YTD)</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ money(totals?.net_income) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ money(totals?.net_income_ytd) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- ── Balance Sheet ── -->
            <div v-else-if="statement === 'balance-sheet'">
                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total Assets</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ money(totals?.assets) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total Liabilities</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ money(totals?.liabilities) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Total Equity</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ money(totals?.equity) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Assets − (Liab + Equity)</div>
                        <div class="mt-1 font-mono text-lg font-semibold" :class="Math.abs(totals?.difference ?? 0) < 0.01 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ money(totals?.difference) }}
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div v-for="sec in bsSections" :key="sec.key" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ sec.title }}</h2>
                        </div>
                        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template v-for="(row, idx) in (sections?.[sec.key] ?? []) as StatementRow[]" :key="idx">
                                    <tr v-if="row.is_group" class="bg-gray-50/70 dark:bg-gray-800/40">
                                        <td class="px-4 py-2.5 font-semibold" :style="row.is_total ? undefined : indent(row)">
                                            <span v-if="!row.is_total && row.code" class="mr-1 font-mono text-xs text-gray-400">{{ row.code }}</span>{{ row.name }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono font-medium text-gray-900 dark:text-white">{{ money(row.current) }}</td>
                                    </tr>
                                    <tr v-else class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-2" :style="indent(row)">
                                            <span class="mr-1 font-mono text-xs text-gray-400">{{ row.code }}</span>{{ row.name }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-gray-900 dark:text-white">{{ money(row.current) }}</td>
                                    </tr>
                                </template>
                                <tr v-if="(sections?.[sec.key] ?? []).length === 0">
                                    <td class="px-4 py-8 text-center text-sm text-gray-400">No {{ sec.title.toLowerCase() }} accounts.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Cash Flow ── -->
            <div v-else-if="statement === 'cash-flow'">
                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Opening Cash</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ money(opening) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Net Change</div>
                        <div class="mt-1 font-mono text-lg font-semibold" :class="(net_change ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ money(net_change) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Closing Cash</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ money(closing) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Reconciliation</div>
                        <div class="mt-1 font-mono text-lg font-semibold" :class="reconciled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ reconciled ? 'In balance' : 'Mismatch' }}
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-semibold">Activity</th>
                                <th class="px-4 py-3 text-right font-semibold">Net Cash Flow</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="row in (rows as CashFlowRow[]) ?? []" :key="row.key" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ row.name }}</td>
                                <td class="px-4 py-2.5 text-right font-mono" :class="row.amount >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'">
                                    {{ money(row.amount) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <td class="px-4 py-3 font-semibold">Net increase in cash</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ money(net_change) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- ── Statement of Equity ── -->
            <div v-else>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-semibold">Account</th>
                                <th class="px-4 py-3 text-right font-semibold">Opening</th>
                                <th class="px-4 py-3 text-right font-semibold">Movement</th>
                                <th class="px-4 py-3 text-right font-semibold">Closing</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="(row, idx) in (rows as EquityRow[]) ?? []" :key="idx" class="hover:bg-gray-50 dark:hover:bg-gray-800/50" :class="{ 'bg-gray-50/70 dark:bg-gray-800/40 font-semibold': row.synthetic }">
                                <td class="px-4 py-2.5">
                                    <span v-if="row.code" class="mr-1 font-mono text-xs text-gray-400">{{ row.code }}</span>{{ row.name }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ money(row.opening) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono" :class="row.movement >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'">{{ money(row.movement) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-white">{{ money(row.closing) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <td class="px-4 py-3 font-semibold">Total equity</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ money(totals?.opening) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ money(totals?.movement) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ money(totals?.closing) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>