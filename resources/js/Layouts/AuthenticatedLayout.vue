<script lang="ts">
// Module-scope cache: the sidebar <nav> is recreated on every Inertia navigation
// (the layout remounts per page), so this state must live OUTSIDE <script setup>
// (which is per-instance) to survive remounts. Restored in onMounted below.
let savedSidebarScroll = 0;
</script>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashMessage from '@/Components/FlashMessage.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const currentCompany = computed(() => page.props.current_company);
const companies = computed(() => page.props.companies ?? []);
const permissions = computed(() => page.props.auth.permissions ?? []);
const unreadNotifications = computed(() => page.props.notifications?.unread_count ?? 0);

const can = (slug: string) => permissions.value.includes(slug) || user.value?.is_super_admin;

const sidebarOpen = ref(false);
const darkMode = ref(localStorage.getItem('theme') === 'dark');

watch(darkMode, (value) => {
    document.documentElement.classList.toggle('dark', value);
    localStorage.setItem('theme', value ? 'dark' : 'light');
}, { immediate: true });

let navElement: HTMLElement | null = null;

const onNavScroll = (event: Event) => {
    savedSidebarScroll = (event.target as HTMLElement).scrollTop;
};

onBeforeUnmount(() => {
    if (navElement) {
        savedSidebarScroll = navElement.scrollTop;
        navElement.removeEventListener('scroll', onNavScroll);
    }
});

const initGroupOpen = (label: string): boolean => {
    const key = `sidebar-open-${label}`;
    const saved = localStorage.getItem(key);
    if (saved !== null) {
        return saved === '1';
    }

    return label === 'Overview' || navGroups.value.some((group) =>
        group.label === label && group.items.some((item) => itemVisible(item) && isActive(item.routeName)),
    );
};

const openGroups = ref<Record<string, boolean>>({});

const toggleGroup = (label: string) => {
    const next = !(openGroups.value[label] ?? false);
    openGroups.value = { ...openGroups.value, [label]: next };
    localStorage.setItem(`sidebar-open-${label}`, next ? '1' : '0');
};

function itemVisible(item: { permission?: string | null }): boolean {
    return !item.permission || can(item.permission);
}

const isActive = (routeName: string) => {
    const current = route().current() ?? null;

    if (current === null) {
        return routeName === 'dashboard';
    }

    const base = routeName.split('.')[0];

    return current === base || current.startsWith(base + '.');
};

const navRef = ref<HTMLElement | null>(null);

watch(navRef, (el) => {
    if (navElement === el) {
        return;
    }
    navElement?.removeEventListener('scroll', onNavScroll);
    navElement = el;
    if (navElement) {
        navElement.addEventListener('scroll', onNavScroll, { passive: true });
        // The layout remounts per navigation; restore the pre-navigation scroll
        // position once the fresh <nav> element is available.
        nextTick(() => {
            if (navElement && savedSidebarScroll > 0 && navElement.scrollTop === 0) {
                navElement.scrollTop = savedSidebarScroll;
            }
        });
    }
});

// computed watch that guarantees openGroups has at least the defaults for all groups,
// and the group containing the active route is expanded.
const ensureOpen = () => {
    const next = { ...openGroups.value };
    for (const group of navGroups.value) {
        if (!(group.label in next)) {
            next[group.label] = initGroupOpen(group.label) || group.items.some((i) => itemVisible(i) && isActive(i.routeName));
        }
    }
    if (!Object.values(next).some(Boolean)) {
        next.Overview = true;
    }
    openGroups.value = next;
};

const navGroups = computed(() => [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', routeName: 'dashboard', icon: 'dashboard', permission: null },
        ],
    },
    {
        label: 'Organization',
        items: [
            { label: 'Companies', routeName: 'companies.index', icon: 'building', permission: 'company.view' },
            { label: 'Branches', routeName: 'branches.index', icon: 'branch', permission: 'branch.view' },
            { label: 'Users', routeName: 'users.index', icon: 'users', permission: 'user.view' },
            { label: 'Roles & Permissions', routeName: 'roles.index', icon: 'shield', permission: 'role.view' },
        ],
    },
    {
        label: 'Accounting Setup',
        items: [
            { label: 'Chart of Accounts', routeName: 'accounts.index', icon: 'account', permission: 'account.view' },
            { label: 'Tax & VAT', routeName: 'tax.index', icon: 'tax', permission: 'tax.view' },
            { label: 'Accounting Settings', routeName: 'accounting-settings.index', icon: 'settings', permission: 'accounting_config.view' },
            { label: 'Fiscal Years', routeName: 'fiscal-years.index', icon: 'calendar', permission: 'fiscal_year.view' },
            { label: 'Accounting Periods', routeName: 'accounting-periods.index', icon: 'period', permission: 'period.view' },
            { label: 'Currencies', routeName: 'currencies.index', icon: 'currency', permission: 'currency.view' },
            { label: 'System Settings', routeName: 'settings.index', icon: 'settings', permission: 'settings.view' },
        ],
    },
    {
        label: 'Master Data',
        items: [
            { label: 'Customers', routeName: 'customers.index', icon: 'customer', permission: 'customer.view' },
            { label: 'Suppliers', routeName: 'suppliers.index', icon: 'supplier', permission: 'supplier.view' },
            { label: 'Products & Services', routeName: 'products.index', icon: 'product', permission: 'product.view' },
            { label: 'Warehouses', routeName: 'warehouses.index', icon: 'warehouse', permission: 'warehouse.view' },
        ],
    },
    {
        label: 'Sales',
        items: [
            { label: 'Sales Invoices', routeName: 'sales.invoices.index', icon: 'invoice', permission: 'sales.view' },
        ],
    },
    {
        label: 'Purchase',
        items: [
            { label: 'Purchase Bills', routeName: 'purchase.bills.index', icon: 'bill', permission: 'purchase.view' },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { label: 'Stock', routeName: 'stock.index', icon: 'stock', permission: 'inventory.view' },
            { label: 'Adjustments', routeName: 'stock-adjustments.index', icon: 'adjust', permission: 'inventory.view' },
            { label: 'Transfers', routeName: 'stock-transfers.index', icon: 'transfer', permission: 'inventory.view' },
        ],
    },
    {
        label: 'Receivables',
        items: [
            { label: 'Overview', routeName: 'receivables.index', icon: 'receipt', permission: 'receivables.view' },
            { label: 'Outstanding', routeName: 'outstanding.index', icon: 'invoice', permission: 'receivables.view' },
            { label: 'Aging Report', routeName: 'aging.index', icon: 'alert', permission: 'receivables.view' },
            { label: 'Record Payment', routeName: 'payment.index', icon: 'payment', permission: 'receipt.post' },
            { label: 'Customer Advances', routeName: 'advances.index', icon: 'currency', permission: 'receivables.view' },
        ],
    },
    {
        label: 'Payables',
        items: [
            { label: 'Overview', routeName: 'payables.index', icon: 'bill', permission: 'payables.view' },
            { label: 'Outstanding', routeName: 'payable-outstanding.index', icon: 'bill', permission: 'payables.view' },
            { label: 'Aging Report', routeName: 'payable-aging.index', icon: 'alert', permission: 'payables.view' },
            { label: 'Record Payment', routeName: 'supplier-payment.index', icon: 'payment', permission: 'payment.post' },
            { label: 'Supplier Advances', routeName: 'supplier-advances.index', icon: 'currency', permission: 'payables.view' },
        ],
    },
    {
        label: 'Cash & Bank',
        items: [
            { label: 'Cash & Bank', routeName: 'cash-bank.index', icon: 'bank', permission: 'bank.view' },
        ],
    },
    {
        label: 'Expenses',
        items: [
            { label: 'Expenses', routeName: 'expenses.index', icon: 'expense', permission: 'expense.view' },
        ],
    },
    {
        label: 'Fixed Assets',
        items: [
            { label: 'Fixed Assets', routeName: 'fixed-assets.index', icon: 'asset', permission: 'fixed_asset.view' },
        ],
    },
    {
        label: 'Payroll',
        items: [
            { label: 'Payroll', routeName: 'payroll.index', icon: 'payroll', permission: 'payroll.view' },
        ],
    },
    {
        label: 'Budget',
        items: [
            { label: 'Budgets', routeName: 'budgets.index', icon: 'budget', permission: 'budget.view' },
        ],
    },
    {
        label: 'Reporting',
        items: [
            { label: 'Reports', routeName: 'reports.index', icon: 'report', permission: 'report.view' },
            { label: 'Statements', routeName: 'statements.index', icon: 'statement', permission: 'report.view' },
        ],
    },
    {
        label: 'Transactions',
        items: [
            { label: 'Journals', routeName: 'journals.index', icon: 'journal', permission: 'journal.view' },
            { label: 'Opening Balances', routeName: 'opening-balances.index', icon: 'opening', permission: 'opening_balance.view' },
        ],
    },
    {
        label: 'Governance',
        items: [
            { label: 'Approvals', routeName: 'approvals.index', icon: 'approval', permission: 'approval.view' },
            { label: 'Approval Workflows', routeName: 'approval-workflows.index', icon: 'workflow', permission: 'approval.view' },
            { label: 'Audit Log', routeName: 'audit.index', icon: 'audit', permission: 'audit.view' },
            { label: 'Notifications', routeName: 'notifications.index', icon: 'bell' },
        ],
    },
]);

const visibleGroups = computed(() => navGroups.value
    .filter((group) => group.items.some((item) => itemVisible(item))));

ensureOpen();

const switchCompany = (id: number) => {
    router.post(route('companies.switch'), { company_id: id }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <!-- Sidebar overlay (mobile) -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-gray-900/60 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sidebar -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0 dark:border-gray-800 dark:bg-gray-900"
        >
            <!-- Sidebar header -->
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 px-5 dark:border-gray-800">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">
                    AE
                </div>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                        Accounting ERP
                    </div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{
                            currentCompany?.name
                                ? `${currentCompany.name} • ${currentCompany.currency_code ?? ''}`
                                : 'No company selected'
                        }}
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav ref="navRef" class="flex-1 overflow-y-auto px-3 py-4">
                <div v-for="group in visibleGroups" :key="group.label" class="mb-1">
                    <!-- Collapsible topic header -->
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                        @click="toggleGroup(group.label)"
                    >
                        <span>{{ group.label }}</span>
                        <svg
                            class="h-3.5 w-3.5 transition-transform duration-200"
                            :class="openGroups[group.label] ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Submenu -->
                    <ul v-show="openGroups[group.label]" class="space-y-0.5">
                        <li
                            v-for="item in group.items.filter((i) => !i.permission || can(i.permission))"
                            :key="item.routeName"
                        >
                            <Link
                                :href="route(item.routeName)"
                                :class="isActive(item.routeName) ? 'active-nav' : 'nav-item'"
                                class="flex items-center gap-2.5 rounded-lg py-2 pl-3 pr-3 text-sm font-medium transition-colors"
                                @click="sidebarOpen = false"
                            >
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-40" />
                                {{ item.label }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Sidebar footer -->
            <div class="border-t border-gray-200 p-3 dark:border-gray-800">
                <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                    Phase 10 · Complete accounting ERP
                </div>
            </div>
        </aside>

        <!-- Main column -->
        <div class="lg:pl-64">
            <!-- Topbar -->
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <button
                    type="button"
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:hidden dark:hover:bg-gray-800"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="hidden text-sm text-gray-500 sm:block dark:text-gray-400">
                    <span v-if="currentCompany" class="font-medium text-gray-700 dark:text-gray-200">
                        {{ currentCompany.legal_name ?? currentCompany.name }}
                    </span>
                </div>

                <div class="ms-auto flex items-center gap-1.5">
                    <!-- Dark mode toggle -->
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        :title="darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                        @click="darkMode = !darkMode"
                    >
                        <svg v-if="darkMode" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.36 6.36l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>

                    <!-- Notifications -->
                    <Link
                        :href="route('notifications.index')"
                        class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        :title="'Notifications'"
                    >
                        <AppIcon name="bell" class="h-5 w-5" />
                        <span
                            v-if="unreadNotifications > 0"
                            class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
                        >{{ unreadNotifications > 9 ? '9+' : unreadNotifications }}</span>
                    </Link>

                    <!-- Company switcher -->
                    <Dropdown v-if="companies.length" align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                <span class="hidden max-w-[10rem] truncate sm:inline">{{ currentCompany?.name ?? 'Select Company' }}</span>
                                <svg class="ms-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                Switch company
                            </div>
                            <button
                                v-for="company in companies"
                                :key="company.id"
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="switchCompany(company.id)"
                            >
                                <span>{{ company.name }}</span>
                                <span
                                    v-if="company.id === currentCompany?.id"
                                    class="text-xs font-medium text-indigo-600 dark:text-indigo-400"
                                >
                                    Active
                                </span>
                            </button>
                        </template>
                    </Dropdown>

                    <!-- User dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg p-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">
                                    {{ user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                                </span>
                                <span class="hidden text-left sm:block">
                                    <span class="block leading-tight">{{ user?.name }}</span>
                                    <span v-if="user?.is_super_admin" class="block text-xs text-gray-400">Super Admin</span>
                                </span>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Flash messages -->
            <FlashMessage />

            <!-- Page content -->
            <main class="px-4 py-6 sm:px-6">
                <Breadcrumbs />
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.nav-item {
    @apply text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white;
}

.active-nav {
    @apply bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300;
}
</style>