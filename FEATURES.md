# Accounting ERP — Feature Inventory (ki ki banano hoyeche)

Multi-company, database-per-tenant accounting ERP। Laravel 11 + Vue 3 (Inertia) + TypeScript +
Tailwind + MySQL (Docker) + SQLite (test)। Platform (control-plane: users/companies/RBAC/audit) +
প্রতিটা কোম্পানির জন্য নিজস্ব ডেটাবেস (`accounting_tenant_<company>`) — সম্পূর্ণ isolation।

> এই ডকটা কোড থেকে যাচাই করা — প্রতিটা ফিচারের পেছনে দারুণ route/controller/মাইগ্রেশন আছে।
> Engineering-lessons ও known-limitation: `AGENTS.md` §1 ও §2 (স্বয়ংক্রিয়ভাবে তাজা থাকে)।

---

## ০. প্ল্যাটফর্ম (Platform / control-plane)

- **Super admin** (platform): যেকোনো কোম্পানিতে ঢুকতে পারে, cross-company কিছুই আটকানো নেই।
- **Database-per-company**: কোম্পানি তৈরি করলেই নিজস্ব DB auto-created → migrated → seeded
  (`accounting_tenant_<snake_case_name>`)। Super admin এখানে কোম্পানির Admin user + password
  পায়। Legacy `{prefix}{id}` DB গুলোকে `tenant:provision`-এ নতুন নামে auto-rename।
- **Multi-DB connection সমাধান** (`SelectTenantDatabase` + `SetActiveCompanyContext` middleware):
  কাস্টমার/প্রোডাক্ট/ইনভয়েস… যেকোনো tenant-রাউটে সঠিক ডেটাবেসে route-model binding — `create →
  show 404` বাগটা দূর (রেজোলিউশন অর্ডার: active-company → select-tenant → route-binding)।
- **Notifications**: top-bar bell + unread bad ⏰, company-scoped inbox, mark-all-read, ইনটেনশন
  ক্যাটাগরি (approval/document status), per-company `data->company_id` scoping।
- **Audit Log**: append-only সব mutation-এর record (module/action/user/IP/changes + before/after
  diff)। Governance → Audit Log।
- **Approval workflow**: amount-module ভিত্তিক multi-step chain; ISO অনুমোদন (approve/reject +
  reason), approver notification, draft→pending→approved। Non-blocking: কোনো matching rule না
  থাকলে আগের মতোই পোস্ট হয়।
- **RBAC**: permission gate (`module.action`), role→permission, sidebar permission-ভিত্তিক
  ফিল্টার, company-scoped role assignment।
- **Currencies** + exchange rates (+ conv rate), **multi-branch** (আলাদা inventory/Db-scope),
  **multi-user + roles/permissions**।

---

## ১. Master Data (Organization/Master Data)

- **Customers** (CUS-code): credit limit, payment terms (days → auto due-date), opening balance,
  AR account, outstanding/terms দেখানো, active/inactive, soft-delete।
- **Suppliers** (SUP-code): mirror of customer (AP দিক)।
- **Products & Services**: `product|service`, sell/purchase price, per-product default tax,
  inventory track toggle, low-stock threshold, item category, unit, stock field, posting-account.
- **Warehouses/Branches**: location-wise stock; company-scoped।
- **Tax & VAT**: tax types + rates (inclusive/exclusive, `output/input` account mapping)।
- **Units**, **Currencies**, **Accounting settings**, **Fiscal years + periods**, **Branches**।

---

## ২. Accounting (core engine)

- **Chart of Accounts**: parent/child tree, `type` (asset/liability/equity/income/expense),
  `normal_balance`, postable-leaf নিয়ম, cycle-guard, code-based auto-numbering, tax/inventory
  account ঘোষণা। Soft-delete + protection (ব্যবহৃত account delete-আটকানো)।
- **Journal**: draft → posted; balanced debit=credit, non-zero lines, 2+ line, only postable
  accounts; `journal_no = {prefix}-{year}-%04d`; automatic debit/credit errors সার্ভারসাইড।
- **Journal source types**: manual, sales_invoice, purchase_bill, expense, journal, bank,
  receipt, supplier_payment, payroll (PYR), salary payment, depreciation (DEP), capitalization
  (FA), disposal (DSP), write-off (WOF), opening (OB), advance/receipt application, budget,
  closing (YEC), transferred… প্রতিটির নিজস্ব prefix+label।
- **Opening balances** (OB) + **fiscal year closing** (YEC: income/expense → retained earnings +
  next-year opening journals), sequential per-company, closed-period posting-lock (§1.18 rule)।
- **Accounting periods**: open/close/lock, `is_open` posting gate, sequence enforcement,
  active-period set для journals।
- **Payroll accrual + payment** journals (PYR), **budget** (planning/locked-on-post), **expense**
  (EXP), **cash/bank** (CBT), **fixed asset** (FA/DEP/DSP) — সব posting-এ GL-journal।

---

## ৩. Sales (AR)

- **Sales Invoices** (SL-{year}) — customer, date/due-date auto-fill (payment terms), lines
  (product/qty/price/discount/tax), totals live-client + strict server-side; draft → posted
  (AR Dr | Revenue Cr + Tax + COGS/Inventory) — all server-recomputed।
- **Receipts** — post payment against invoice(s) (`receipt`), advance receipt (Cash Dr |
  Customer Advances Cr), advance-application (Customer Advances Dr | AR Cr), numbering
  `RC`/`RCA`। Multi-invoice allocation, per-invoice `amount_paid` increment, over-allocation
  guard (`ReceivablePostingException`)।
- **Customer Advances** (advance receipt + application), **Write-off** (Bad Debt Dr | AR Cr,
  WOF), **AR Dashboard/Overdue/Aging**, **Record Payment**, **Statement**।
- **Outstanding / Separate Payment**: allocate received money to invoices; fully matching check.

---

## ৪. Purchase / Payables (AP)

- **Purchase Bills** (PB-{year} + bill_no) — supplier, bill date/due-date auto-fill, lines
  (product/qty/cost/discount → +tax), totals; draft → posted (Inventory/Expense Dr | Input Tax
  Dr | AP Cr) — server-side, postable-leaf required।
- **Supplier Payments** (multi-bill allocation, `PY` payment numbering — shared sequence),
  **Supplier Advances** (`SA`, Cash/Bank Dr | Suppliers Advances Cr + application/payment-arr),
  **AP Dashboard/Outstanding/Aging**, **Record Payment** + Bill-level allocation.
- **Payables module**: outstanding list, aging report, advance-apply, watching, `payables.view/
  advance` permissions (per-company)।

---

## ৫. Inventory / Stock

- **Stock ledger** (`stock_movements`, weighted-average, permission-aware) — on-hand, avg-cost,
  costFor() fallback, $ stock-movement journal-integration (purchase receipts / sales issue)।
- **Stock page**: on-hand per warehouse, low-stock red highlight, summary cards, product →
  products.edit।
- **Stock Adjustments** (ADJ journal, inventory account Dr/Cr): draft → posted, deltas recomputed
  at post, requirement postable-leaf; numbering `ADJ-{year}`।
- **Stock Transfers** (source→dest warehouse, `TR-{year}`) + Stock Moves per warehouse (transfer
  in/out), insufficient stock guard (`StockPostingException`)।
- **No batch/serial/expiry, FIFO/weighted-avg per product, GRN as separate doc — deferred** (নোট):
  à point field present; docs — see AGENTS §Phase 7 note.

---

## ৬. Cash & Bank

- **Cash & Bank**: account list (cash + bank, with GL account), transactions (cash receipts/
  payments, bank deposit/withdrawal/transfer/inter-charge/interest), all as posted journals.
- **CSV bank reconciliation**: upload statement → auto-match by date+amount → reconciliations
  review (match/unmatch), complete when all lines matched, posted спис walks closed-period lock
  (last reconciled date lock) — file stays uploads disk.
- **Bank Reconciliation** page + transaction grouping. USB/Biraj nice.

---

## ৭. Expenses

- **Expense categories + expenses**: draft→posted (Expense Dr | Tax-in | Cash/Bank/AP Cr),
  recurring expense generation (auto-create-next-draft), category master, `EXP-{year}` numbering,
  attachment support. Cash/Bank/AP auto-post.

---

## ৮. Fixed Assets

- **Asset categories** (account mapping + default depreciation method/life), **Fixed assets**
  (register): draft → capitalize (FA journal), **depreciation** (DEP batch per period, SL/DB,
  idempotent per asset+period), **disposal** (DSP journal, Gain/Loss), net book value /
  accumulated depreciation accounts. Fixed Asset + Depreciation pages.

---

## ৯. Payroll

- **Departments/designations** (active/inactive), **employees** (department/designation,
  join date, basic structure), **salary structure** (basic + allowances + deductions → gross/
  net), **payroll runs** (process per period: draft→post → PYR journal Salary-Expense Dr |
  Payable Cr), **salary payment** (Cash/Bank Dr | Salary-Payable Cr). Payroll index + run show.

---

## ১০. Budget

- **Budgets**: per fiscal-year; lines (account+period+amount), server validate account-type
  (income/expense leaf), consistent/balanced, `postBudget` → locked draft, variance vs actual
  (from posted journals per period), Budget vs Actual report. `budget.*` permission module.

---

## ১১. Reporting (Reports/Statements)

- **Reports**: GL, Trial Balance, (filters by period/FY).
- **Statements**: Income Statement (current + YTD columns), Balance Sheet (assets/liab/equity
  with synthetic current-year earnings), Cash Flow (direct, by cash-account code), Statement of
  Equity, Statements page tabs.
- **Audit**, **Approvals** (governance tab), **Dashboard** (company/Basis/FY/active period +
  KPI + trial balance health + AR/AP outstanding + recent journals), **Dashboard KPI** cards.

---

## ১২. Governance / Setup

- **Companies** (super-admin only), **Branches**, **Users**, **Roles & Permissions** (RBAC
  module), **Approval workflows** (module 27), **Tax & VAT**, **Fiscal years/periods**,
  **Opening balances**, **Accounting settings**, **Currencies**, **System Settings**,
  **Reporting**.

---

## Cross-cutting / Engineering

- **Tenant isolation**: প্রতিটা কোম্পানির DB আলাদা — master data ও audit সব বসে নিজস্ব DB-তে,
  cross-company ঢোকার লক (SessionContext/SelectTenant/SubstituteBindings priority)।
- **Permission-by-role + super-admin bypass**; `company-admin` role সব permission (sidebar +
  route gate), accountant/viewer প্রভৃতি প্রিসিড।
- **Audit log** প্রতিটা write-এ (created/updated user + before/after)।
- **Test-suite**: 299 PHP feature tests (2828 assertions) + frontend unit test (vitest,
  InvoiceForm/BillForm specs) — সব green।
- **UI**: Inertia Vue3 + Tailwind dark mode, responsive tables (scrollable), modal CRUD
  (Currencies/Tax rates/Units/Categories), breadcrumb + sidebar permission-aware, formatMoney
  utils। Ziggy-listed routes bhind Inertia `<Link>` links (SPA navigation)।

---

*এই ডকটি `FEATURES.md`-এ gen করা হয়েছে এই সেশনে; প্রতিটা বুলেট কোড থেকে যাচাই। পরবর্তী
changes এই ফাইলে আপডেট করবে — তবে পরম correct source controller/route-তেই।*
