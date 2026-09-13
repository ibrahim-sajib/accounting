# Project Memory — Accounting ERP

This file is the durable memory for this codebase. It records every non-obvious fix,
bug pattern, and the engineering conventions to follow. Read it before making any change,
and append to it whenever you discover a new lesson.

All commands run from the project root (`/Users/mdibrahim/sajib/project/accounting/`).

---

## 1. Bug patterns (fixed — do not regress)

### 1.1 Laravel paginator vs. Inertia Vue props
- Laravel's `LengthAwarePaginator::toArray()` serializes `total` at the **top level**
  (`data`, `links`, `current_page`, ..., `total`). There is **NO** `meta` key.
- Vue index pages must read `records.total` / `records.data` / `records.links`,
  **never** `records.meta.total`. Both `.meta.total` usages previously crashed the SPA
  with `Cannot read properties of undefined (reading 'total')` → blank page on navigation
  (fixed in `Companies/Index.vue`, `Branches/Index.vue`).
- Regression test: `Phase1CoreTest` asserts `->has('companies.data') ->has('companies.links') ->has('companies.total')`.

### 1.2 `withQueryString()` is a Paginator method
- You may only call `->withQueryString()` on the result of `->paginate()`, never on an
  Eloquent Builder. `AccountingPeriodController` chained it after `query()` + `get()` →
  `Call to undefined method Builder::withQueryString()` → 500. Removed it.

### 1.3 PHP-in-shell quoting (Docker entrypoint)
- In a shell single-quoted PHP string, `$argv[1]` is NOT interpolated — it becomes the
  literal host name `$argv[1]`. Use `getenv()` inside the PHP, e.g.
  `new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "3306"), ...)`
  (see `docker/app/entrypoint.sh`).

### 1.4 APP_URL must match the served port
- Ziggy generates absolute URLs from `APP_URL` via `url()`. If the app is served on
  port 8000 but `APP_URL=http://localhost`, every `route()` link targets the wrong
  origin → navigation appears broken. Keep `.env`, `.env.example`, `.env.docker` in sync
  (`APP_URL=http://localhost:8000`).

### 1.5 Inertia SPA navigation ≠ full page loads
- Any plain `<a href>` inside the SPA triggers a full reload (breaks the "exists only once"
  app chrome + fiddly focus states). Use Inertia `<Link :href="route(...)">` everywhere
  (fixed: `PageHeader` back-link).
- `route().current()` can return null → guard it, e.g. `route().current() ?? ''` in inputs.

### 1.6 TypeScript/Inertia type plumbing
- `resources/js/types/index.d.ts` must **export** `PageProps`; the global in
  `resources/js/types/global.d.ts` merges it into `@inertiajs/core`:
  `declare module '@inertiajs/core' { interface PageProps extends InertiaPageProps, AppPageProps {} }`.
- `AppIcon` cannot declare a `class` prop (reserved by Vue) — accept `name` and use fallthrough attrs.
- All shared props (auth, current_company, companies, flash) flow through `HandleInertiaRequests::share()`.

### 1.7 Enum serialization
- Never `json_encode(Suit::cases())` directly. Use the `enum_options(SomeEnum::class)` helper
  (`app/Support/helpers.php`) → `[{value, label}]` for the UI; not the raw `cases()` shape.

### 1.8 Required-field markers + client validation
- `InputLabel` supports a `required` prop that renders a red `*`. Every form field that is
  required server-side must also carry `required` on the `<TextInput>`/`<select>` (forms use
  `@submit.prevent`, so the browser's constraint validation blocks bad submits before the event fires).
- Password/confirmation fields: `:required="!user"` on Edit forms.

### 1.9 PHP tests cannot catch Vue runtime crashes
- Jsdom-free PHP/Inertia assertions only verify server response shape. Render-time crashes
  (like §1.1) only surface in a real browser. Always, after UI work, do a headless Chrome
  navigation pass (login → click every sidebar link → assert heading + no console errors)
  using the CDP approach (see section 3).

### 1.10 Chart of Accounts hierarchy invariants (Phase 2)
- An account is **postable** iff it has no children; `is_postable` is recomputed in BOTH
  directions by `AccountService::syncPostability()` after create/update/delete (a leaf that
  gains a child stops being postable; an account whose children are gone becomes postable again).
- `type` and `normal_balance` are **inherited from the parent**; `level` = parent depth + 1.
  All computed in `AccountService`, never trusted from raw input.
- Cycle guard: `parent_id` may never be self or a descendant. `AccountRequest` uses
  `Rule::notIn([self] + descendantIds())` where `Account::descendantIds()` walks ALL nested
  children (not just direct — a shallow `children()->pluck('id')` check misses grandchildren).
- Delete guard: refuse to delete system accounts, accounts with children (`withTrashed()`),
  or accounts referenced by `tax_rates.input/output_account_id` or `accounting_settings`
  default-account columns (see `AccountService::protectionReason`, thrown as
  `AccountProtectedException` → caught in controller → `back()->with('error', ...)`).

### 1.11 MySQL JSON columns with array casts
- A `json` column cast `'array'` stores `json_encode($value)`. If the controller passes a JSON
  **string** (e.g. a textarea's contents), Laravel double-encodes it. Decode before persisting:
  `if (is_string($v) && is_json) $v = json_decode($v, true);` (see `AccountingSettingController::update`).

### 1.12 Vue type-check gotchas (vue-tsc)
- `@input="$event.target.value"` fails `vue-tsc` (`$event.target is possibly null`). Use a typed
  handler: `const onX = (e: Event) => onSearch((e.target as HTMLInputElement).value);`.
- `lodash` is NOT installed — do not `import { debounce } from 'lodash'` (vite build fails on
  unresolved import). Either use the reusable `SearchInput` component (which debounces AND
  triggers a server-side `router.get`) or a plain local ref for pure client-side filters.
- Unknown props on components (e.g. a "backRoute" convenience you invented) fail `vue-tsc`.
  Only pass props declared by `defineProps`.
- New `AppIcon` paths (account, tax) were added to the `paths` map in `components/AppIcon.vue`.

### 1.13 Shell-glob copy skips dotfiles + stale `public/hot`
- macOS `cp -r src/* dest/` (and Finder drag) omits dotfiles (`.*`). After restructuring the
  repo root from `app/`, `.env`, `.env.docker`, `.gitignore`, `.dockerignore`, `.editorconfig`,
  and `public/hot` were silently lost, causing Docker failures and a broken dev server path.
  Always use `cp -a src/ dest/` or `rsync -a` when moving a Laravel tree.
- A leftover `public/hot` pointing to a non-running Vite dev server makes Laravel load assets
  from `http://[::1]:5173` instead of the compiled `public/build/` → empty `#app`, blank SPA.
  Delete `public/hot` in any production/test-like context: `rm -f public/hot`.

### 1.14 Nested UI modals + no-op navigation
- `Currencies/Index.vue` is the reference for modal-based single-page CRUD (create/edit in a
  `Modal`, sub-resource in a second modal, e.g. Tax types + their rates in `Tax/Index.vue`).
- In an SPA nav test, don't hardcode row identifiers (e.g. account code `9001`) — rerunning
  against a persistent DB collides with the unique constraint. Generate unique values per run.

### 1.15 Date formatting on frontend
- Laravel's `'date'` cast serializes as ISO-8601 (`2026-01-01T00:00:00.000000Z`). Vue templates
  must never render raw dates — use the shared `resources/js/utils/formatDate.ts` utility
  (`Intl.DateTimeFormat`, en-US, UTC, "MMM DD, YYYY" → "Jan 1, 2026").
- Applied to: FiscalYears, Periods, Tax rate effective_date columns. Any future date column
  must use `formatDate()`.

### 1.16 Empty form numbers → null → NOT NULL 500 (MySQL strict)
- `ConvertEmptyStringsToNull` turns a cleared/blank number input into `null`. Inserting that
  `null` into a NOT NULL `decimal`/`integer` column (even one with a `default`) blows up in
  MySQL strict mode: e.g. `SQLSTATE 1048 Column 'payment_terms_days' cannot be null`.
- Fix at the request boundary, NOT in migrations (already-run migrations must not be edited —
  the container tracks them by filename): `prepareForValidation()` replaces `null` with `0`
  per numeric field (`Customer/Supplier/ProductRequest`).
- PHP tests only catch this if they post the *empty string*, not a concrete number — every
  optional numeric is a regression risk. Unit tests + `Index`/`Create` forms use
  `String(props.x ?? '0')` so the initial submit is never empty; the server guard covers the
  user-clears-the-field case.

### 1.17 Sidebar active state must match the route *base*, not the index route
- `route().current('customers.index*')` only matches `customers.index` — never
  `customers.create`/`customers.edit`, so sub-pages lose their nav highlight.
- `AuthenticatedLayout::isActive(routeName)` splits on the first dot and tests
  `current === base || current.startsWith(base + '.')`, so every page under a module
  highlights its menu item (Dashboard keeps a null-current fallback).

### 1.18 Journal numbering is assigned ONLY at posting; drafts carry a null `journal_no`
- Posting is the single moment a journal earns a number (`journal_no` **must be nullable** or
  draft creation 500s on NOT NULL). `JournalPostingService::nextJournalNumber()` emits
  `{prefix}-{year}-{0001}` (prefix `GJ` manual, `OB` opening; sequence zero-padded 4) scoped to
  company + source-prefix + year, inside the same transaction as the status flip.
- Eloquent 500s on eager-loading a relation you only referenced in `->load()` but never defined
  (`RelationNotFoundException: Call to undefined relationship [createdBy]`) surface only on the
  route that loads it — always add the relation methods (`createdBy()`, `postedBy()`) when you
  plan to `load`/`with` them.

### 1.19 Reversal journals are posted opposites, linked through `reversed_journal_id`
- Reversing a posted journal creates a NEW posted journal with swapped debit/credit lines and
  marks the original `status=reversed`. Both rows point at each other via `reversed_journal_id`
  (the reversal's `origin` = original; original's `reversal` = reversal). Reversal is forbidden
  once a journal is already `reversed`; re-posting, editing, and deleting are only allowed for
  `draft`. `JournalController::reverse` uses permission `journal.post` (no separate `void` flow
  yet).

### 1.20 Eloquent createMany/belongsToMany need the FK named after the RELATION, not the table
- `$invoice->lines()->createMany($rows)` inserts `sales_invoice_id` (from the relation's
  singular model name + `_id`) regardless of the table name. Naming the column `invoice_id` blew
  up in SQLite tests before the migration ever ran in MySQL (`table sales_invoice_lines has no
  column named sales_invoice_id`).
- Rule: match Eloquent defaults AND be explicit — line tables use `<model>_id`
  (`sales_invoice_id`), pivot many-to-many FKs passed as explicit args:
  `belongsToMany(Receipt::class, 'receipt_allocations', 'sales_invoice_id', 'receipt_id')`.
Safe to rename a migration's column BEFORE it has run in MySQL (SQLite runs from scratch per
   test); never rename one that Docker has already applied (§1.16).

### 1.21 Never wrap `wherePivot` in a `when()` closure on a belongsToMany (SQL corruption → 500)
- `$user->roles()->when($companyId, fn ($q) => $q->wherePivot('company_id', $companyId))`
  corrupts the where clause into `and \`pivot\` = company_id` → `SQLSTATE[42S22] Unknown column
  'pivot'`. The closure receives the BASE Query\Builder (via the relation's `when` proxy), where
  `wherePivot` resolves to nothing sensible, and the clause is applied twice.
- Branch instead: `$q = $this->roles(); if ($companyId) { $q = $q->wherePivot('company_id', $companyId); }`.
  Calling `->wherePivot()` directly on the relation (no `when`/`clone` wrapper) works fine, even
  followed by `->pluck()`/`->get()` — but `clone()` of the relation builder also reproduces the
  corruption.
- This was a latent RBAC bug: for ANY non-super-admin with an active company context,
  `User::hasPermission()` 500'd on every `permission:`-gated route; only verified now that
  Phase 6 added a positive non-super-admin test (accountant CRUD). Always add a positive
  permission test per module, not just the negative 403 case.

### 1.22 SQLite tolerates selecting NONEXISTENT columns; MySQL 500s
- Selecting a column the table does not have — e.g. `->get(['id','code','name','credit_limit',
  'payment_terms_days'])` on `suppliers` when `credit_limit` lives only on `customers` — PASSES
  every PHPUnit test: SQLite is dynamically typed and returns NULL for unknown selected columns,
  so `Supplier::query()->get(['credit_limit'])` works there. MySQL errors on the very same query:
  `SQLSTATE[42S22] Unknown column 'credit_limit' in 'field list'` → the page 500s in the real app.
- This is why the SPA navigation pass (§3.3) is mandatory: `PayableController::supplierOptions()`
  (copied from `ReceivableController::customerOptions()` but not adapted to the supplier schema)
  crashed /payables, /payables/outstanding, /payables/record-payment and /payables/advances in
  Docker while all 13 Phase 9 PHPUnit tests stayed green. When mirroring an AR page/service into
  AP (or any domain), audit every selected + mapped column against the receiving table's migration.

### 1.23 Eloquent FK naming hit twice + `whereDate` with Carbon objects
- §1.20 struck again in Cash & Bank: `bank_statement_lines` was created with column `import_id`, but
  `BankStatementImport::lines()` (`hasMany`) derives `bank_statement_import_id` → `no such column`
  at runtime in SQLite tests. Renamed the migration column BEFORE Docker had applied it (safe per §1.20).
  Rule: when the child table's real column is NOT `<singular_model>_id`, declare the FK explicitly on
  BOTH sides (`hasMany(..., 'bank_statement_import_id')` + `belongsTo(..., 'bank_statement_import_id')`).
- `whereDate('col', $carbon)` where `$carbon` is a `Carbon` (e.g. from a `'date'` cast) serialises to a
  datetime string (`2026-09-10 00:00:00`) — SQLite compares `date(col) = '2026-09-10 00:00:00'` as a
  STRING → no match, so bank-statement auto-match silently matched 0 lines while plain-date queries
  matched. Always normalise: `$line->line_date->toDateString()` and
  `Carbon::parse($date)->toDateString()` before `whereDate`.
- `PHPUnit` + a `decimal` `amount` column: auto-match equality `where('amount', 500.0)` works in both
  SQLite and MySQL when the stored value is exactly 500.0; keep CSV amounts on the same magnitude.

### 1.24 Vue option-list props must be `[{value, label}]`, never raw Eloquent models
- Passing `Account::query()->get(['id','name'])` (or `name`/`account_name` on bank accounts) straight
  into a `<select v-for="o in opts">` that reads `o.value`/`o.label` renders BLANK options — no crash,
  no console error, the select just contains empty `<option>`s. Every option list in a `formProps()`
  must be explicitly mapped: `->get([...])->map(fn ($m) => ['value' => $m->id, 'label' => ...])->values()->all()`.
  PHP/Inertia tests only assert `has('cashAccounts')` so they stay green; only the headless browser
  pass reveals the blank dropdown → the create form silently can't submit a valid option (Expense §1.22-style).
- Watch SELECTED COLUMNS vs the receiving table too: selecting `currency_id` from `cash_accounts`
  (it lives only on `bank_accounts`) passes SQLite, 500s in MySQL (§1.22) — audit every selected+cast
  column against the migration.

### 1.25 `AuditLogger::log()` argument order
- Signature is `log(module: string, action: string, recordType: ?string=null, recordId: ?int=null,
  oldValues: array=[], newValues: array=[], companyId: ?int=null)`. The 3rd arg is a TYPE STRING,
  NOT the old record. Passing `$model->getOriginal()` as the 3rd arg throws
  `Argument #3 ($recordType) must be of type ?string, array given` at runtime. Correct pattern:
  `AuditLogger::log('expense', 'update', null, $expense->id, $expense->getOriginal(), $expense->fresh()->toArray(), $expense->company_id)`.

### 1.26 Eloquent relation keys in Inertia props are snake_case — Vue reads camelCase
- Passing a raw `$model` (even with `->load('salaryStructure')`) as an Inertia prop serializes the
  relation under its snake_case key (`employee.salary_structure`), so `props.employee.salaryStructure`
  in `.vue` is `undefined` → the salary card rendered `Basic/Gross/Net` with **all zeros** (no error,
  no crash — just silent 0.00 everywhere). Top-level/table columns (employee.name, join_date …) and
  same-name relations (department, designation) are fine; only the camelCased relation key breaks.
- Fix: never pass the raw model when Vue expects a differently-named key — map explicitly in the
  controller (`EmployeeController::serialize()`) to `['id','name','email','phone','join_date',
  'is_active','department_id','designation_id','department'=>['id','name'],'designation'=>['id','name'],
  'salaryStructure'=>...,]`. Lock it in the test: `->has('employee.salaryStructure')->where(
  'employee.salaryStructure.basic','50000.0000')` (this bug slipped past PHPInertia assertions `has('employee')`).

---

## 2. Engineering conventions (senior baseline)

- **Domain-first layout** per `accounting-erp-architecture.md` §4: every module lives in
  `app/Domain/<Module>/` with `Http/Controllers`, `Http/Requests`, `Models`, `Services`, `Enums`.
- **Thin controllers**: validate via Form Request, call an Action/Service, audit-log, redirect.
  No business rules in controllers.
- **Form Requests** gate via `authorize()` (super-admin bypass) + `rules()`/`prepareForValidation()`.
- **Idempotent seeders** (`firstOrCreate`) — safe to re-run (Docker boot re-seeds).
- **Tenant scoping**: every tenant table has `company_id` (indexed); every table has `created_by`,
  `updated_by`, timestamps, soft delete (`deleted_at`) unless it's an append-only log.
- **Money**: `decimal(18,4)`; rates/quantities `decimal(18,6)`; never float.
- **Enums over magic strings**: `app/Support/Enums/*` backed enums, exposed via `enum_options()`.
- **Permissions**: `PermissionSeeder` defines `module.action` slugs; routes use
  `->middleware('permission:module.action')`; `EnsurePermission` bypasses for super admins and
  checks against the active company. Sidebar items are filtered by `can()` which also bypasses
  super admins.
- **New-company provisioning**: `CompanyController::provisionDefaults()` runs the
  Currency/FiscalYear/SystemSetting seeders (and since Phase 2 also
  ChartOfAccounts/Tax/AccountingSetting) so every new company starts whole.
- **Phase 2 domains added**: `app/Domain/Tax/` (TaxType + TaxRate) and the CoA +
  `AccountingSetting` live in `app/Domain/Accounting/` alongside FiscalYear/Period.
  New permission module `accounting_config.view|update` was added to `PermissionSeeder`
  and granted to company-admin (`*`), accountant (view+update) and viewer (view).
  Default COA/tax/accounting-settings live in `database/seeders/ChartOfAccountsSeeder.php`,
  `TaxSeeder.php`, `AccountingSettingSeeder.php` (all idempotent, wired into
  `DatabaseSeeder` + `provisionDefaults`).
- **Phase 3 domains added (Master Data)**: `app/Domain/Party/` (Customer + Supplier),
  `app/Domain/Product/` (Product + ProductCategory + Unit), `app/Domain/Warehouse/`.
  - Permission modules `customer|supplier|product|warehouse` (view/create/update/delete)
    were already in `PermissionSeeder`/`RoleSeeder` from the skeleton — no permission
    migration needed; company-admin gets `*`, accountant/inventory-manager/viewer get
    view (product gets create too for inventory-manager).
  - Customers/Suppliers carry optional `ar_account_id`/`ap_account_id` that default to
    `accounting_settings.default_ar/ap_account_id` (passed as a `defaultArAccountId`
    prop; form posts `''` when "Use company default" is chosen, but the controller
    persists the *setting* default via the seeded prop since DB stores nullable FK).
    Option lists are `[{value, label}]` (`code — name`) filtered to postable accounts.
  - Products resolve default posting accounts from `accounting_settings` (inventory,
    sales, purchase) + hardcoded COGS = account code `5221` (there is **no**
    `default_cogs_account_id` in settings) — see `ProductController::defaultAccounts()`.
    Product types use `ProductType` enum (`product|service`); service products hide
    unit/inventory/COGS fields on the form.
  - Products page embeds category + unit sub-resource management as modals (same
    pattern as Tax rates), routes `/product-categories` and `/units`. Delete guards:
    category in use by a product → refused with `error`; unit in use → refused.
  - `MasterDataSeeder` seeds default categories (Goods/Services/Raw Materials) and units
    (pc/kg/L/bx/hr), wired into `DatabaseSeeder` + `provisionDefaults` for new companies.
- **Phase 4 domains added (Accounting engine)**: `Journal`/`JournalLine`/
  `OpeningBalance` models live in `app/Domain/Accounting/Models/`; business rules live in
  `app/Domain/Accounting/Services/JournalPostingService.php` + `JournalSourceType` enum
  (`manual|opening|...` — `prefix()` returns `GJ`/`OB`). `JournalPostingException`
  (`app/Domain/Accounting/Exceptions/`) is caught in controllers → `back()->with('error', ...)`.
  - `journals.journal_no` is **nullable** (drafts have none); the number is generated by
    `nextJournalNumber()` only when a journal reaches `posted`. Opening journals are dated the
    fiscal year's first day, `source_type=opening`, and are the ONLY opening post allowed per
    fiscal year (re-post rejected).
  - Journal status uses the existing `TransactionStatus` enum. Lifecycle: `draft` (store/update/
    delete allowed) → `posted` (post) → `reversed` (reverse). Reverse = new posted journal with
    swapped debit/credit lines; both rows link via `reversed_journal_id` (§1.19).
  - Posting guards (all raise `JournalPostingException`): ≥2 lines, a non-zero amount per line,
    balanced debits=credits (±0.0001), only `is_postable` leaf + active accounts, and an **open**
    accounting period covering the journal date (`AccountingPeriod::isOpen()`); reversal may
    pass a closed period for super admins only.
  - Opening balances are staged per fiscal year in `opening_balances` (`draft`), validated
    balanced on post, and finalization **mirrors the journal lines exactly** (rows upserted to
    `status=posted` + `journal_id`; rows not in the journal deleted), so the entry screen always
    matches what was posted.
  - Permissions: new module `opening_balance` (`view|create|post`); journals reuse the existing
    `journal` module (`journal.post` covers reverse). RoleSeeder: accountant gets
    `opening_balance.*`, viewer gets `opening_balance.view`. `CompanyController::provisionDefaults()`
    re-seeds permissions on every new company (already wired).
  - Routes add a "Transactions" sidebar group (Journals + Opening Balances) + breadcrumb map
    entries. Journal numbers/quotes show `formatMoney()` (`resources/js/utils/formatMoney.ts`);
    `StatusBadge` gained the `reversed` color. Opening balance entry rows keep amounts as
    strings client-side and are normalized by the request's `prepareForValidation()` (§1.16).
- **Phase 5 domains added (Sales — accounting core)**: `app/Domain/Sales/` holds `SalesInvoice`
  (+`SalesInvoiceLine`), `Receipt` (+`ReceiptAllocation`), `SalesInvoiceService`,
  `SalesInvoiceController`, `SalesInvoiceRequest`/`ReceiptRequest`, `SalesPostingException`.
  Scope: invoices (draft → posted `SINV` journal) + customer receipts ("Record Payment" →
  `RCT` journal). No quotation/order/delivery/credit-note yet.
  - **Numbering is assigned at posting** (mirrors §1.18): `invoice_no = SL-{year}-%04d`,
    `receipt_no = RC-{year}-%04d`, journal numbers via `JournalSourceType::prefix()`
    (`sales_invoice → SINV`, `receipt → RCT`), all inside the posting transaction, unique
    per company. `sales_invoices.invoice_no` is nullable; drafts carry a `draft` status.
  - **Posting recomputes every amount server-side** from the persisted lines
    (`SalesInvoiceService::buildJournalLines`) — never trusts client math. Invoice journal:
    AR Dr | Sales Revenue Cr | Output Tax Cr, plus COGS Dr | Inventory Cr for each
    product-type line (COGS fallback = account code `5221`; inventory/sales/AR fall back to
    `accounting_settings` defaults; output tax via `tax_rate.output_account_id` → default).
    All GL accounts must be postable leaves, else `SalesPostingException`.
  - **Tax is treated as EXCLUSIVE** in the journal regardless of the rate's `is_inclusive`
    flag (seeded VAT is inclusive — a future refinement should extract VAT inside inclusive
    prices; the current UI + journal treat `total + tax%` consistently).
  - **Payments**: single-invoice allocation only (multi-alloc deferred to Phase 8 AR); require a
    **posted** invoice and `amount ≤ balanceDue()` (overpayment rejected); receipt row is created
    already `posted` and `invoice.amount_paid` is incremented. `paid_state` is derived
    (`unpaid|partial|paid`); `overdue` = posted && amount_paid < total && due_date < today.
    Index list status filters (unpaid/partial/paid/overdue) are derived in
    `SalesInvoiceController::applyStatusFilter()`.
  - **Account resolution order**: customer `ar_account_id` → settings `default_ar_account_id`;
    product `sales_account_id` → default; line's explicit `tax_rate_id` → made-inline when blank
    from the product. The controller (`paymentAccountOptions()`) offers only default
    cash/bank (+ codes `1111`/`1112`/`1113`) postable leaves for payment accounts.
  - **Draft-only mutations**: edit/update/delete 422 on posted invoices; `sales.invoices.pay`
    route is gated by `receipt.post`; posting gated by `sales.post`. RoleSeeder: accountant
    gained `sales.create/update/delete` (was view+post), viewer gained `receipt.view`.
  - **UI**: `Pages/Sales/Invoices/{Index,Create,Edit,Show,InvoiceForm}.vue` with a line editor
    (product select prefills price + default tax, live client totals) + a "Record Payment"
    `Modal` on Show. Sidebar has a "Sales" group; breadcrumbs map `sales` module labels
    (supports 3-segment names like `sales.invoices.create`); `AppIcon` gained `invoice`/`receipt`;
    `StatusBadge` gained `paid|partial|unpaid|overdue`; `Journals/Show` links to the source
    invoice for `sales_invoice` journals (uses `journal.source_id`).
- **Phase 6 domains added (Purchase — accounting core)**: `app/Domain/Purchase/` holds
  `PurchaseBill` (+`PurchaseBillLine`), `SupplierPayment` (+`SupplierPaymentAllocation`),
  `PurchaseBillService`, `PurchaseBillController`,
  `PurchaseBillRequest`/`SupplierPaymentRequest`, `PurchasePostingException`. Scope: purchase
  bills (draft → posted `PUR` journal) + supplier payments ("Record Payment" → `PMT` journal).
  No requisition/PO/GRN/debit-notes yet (GRN→stock lands with Phase 7 Inventory).
  - **Numbering is assigned at posting** (mirrors §1.18): `bill_no = PB-{year}-%04d`,
    `payment_no = PY-{year}-%04d`, journal numbers via `JournalSourceType::prefix()`
    (`purchase_bill → PUR`, `payment → PMT`), inside the posting transaction, unique per
    company; `bill_no` nullable on drafts.
  - **Posting recomputes every amount server-side** (`PurchaseBillService::buildJournalLines`)
    — never trusts client math. Bill journal: Inventory/Expense Dr | Input Tax Dr | AP Cr.
    Account resolution: product `purchase_account_id` → setting `default_purchase_account_id`;
    AP = supplier `ap_account_id` → setting `default_ap_account_id`; input tax via
    `tax_rate.input_account_id` → setting `default_tax_input_account_id`. Postable-leaf
    requirement enforced (`PurchasePostingException`). Supplier tagged as `party_type=supplier`.
  - **Payments**: single-bill allocation only; require a **posted** bill and `amount ≤
    balanceDue()` (overpayment rejected); payment row created already `posted`; `amount_paid`
    incremented. `paid_state` derived (`unpaid|partial|paid`); `overdue` = posted && not paid &&
    due_date < today. Index filters in `PurchaseBillController::applyStatusFilter()`.
  - **Draft-only mutations**: edit/update/delete 422 on posted bills; `purchase.bills.pay`
    route gated by `payment.post`; posting gated by `purchase.post`. RoleSeeder: accountant
    gained `purchase.create/update/delete`, viewer gained `payment.view` (purchase/payment
    permission modules already existed).
  - **UI**: `Pages/Purchase/Bills/{Index,Create,Edit,Show,BillForm}.vue` — line editor prefills
    `unit_cost` from product `purchase_price` + default tax; Show has a "Record Payment" `Modal`
    (balance-due default). Sidebar "Purchase" group; breadcrumbs `purchase` module labels
    (3-segment `purchase.bills.create`); `AppIcon` gained `bill`/`payment`; `Journals/Show`
    links to the source bill for `purchase_bill` journals ("View Bill").
- **Phase 7 domains added (Inventory — stock ledger)**: `app/Domain/Inventory/` holds
  `StockMovement` (append-only ledger, no soft deletes), `StockAdjustment` (+`StockAdjustmentLine`),
  `StockTransfer` (+`StockTransferLine`), `StockService`,
  `StockController`/`StockAdjustmentController`/`StockTransferController`,
  `StockAdjustmentRequest`/`StockTransferRequest`, `StockPostingException`. Scope: a
  weighted-average stock ledger, the Stock page, stock adjustments (draft → posted `ADJ` journal),
  stock transfers (no journal), plus stock-in/out wired into posted Purchase Bills / Sales
  Invoices. No batch/serial/expiry, FIFO vs weighted-average per product, reservations
  (Reserved Qty is 0 and unused), dedicated Opening Stock doc, sales returns/debit-note stock
  reversal, or GRN as a separate doc yet.
  - **Ledger**: `stock_movements` is append-only — one signed `quantity` row per event
    (`opening|purchase_received|sales_issued|adjustment|transfer_out|transfer_in`). `onHandQty()`
    = `SUM(quantity)` (optionally scoped to a warehouse); **weighted-average cost** =
    Σ(`line_value`) ÷ Σ(`quantity`); `costFor()` falls back to `product.purchase_price` when
    nothing is in stock (keeps the pre-stock Phase 5 COGS behaviour when receipts never happened).
    Purchases cost receipts at `net line total ÷ quantity` so the ledger value mirrors the PUR
    journal's Inventory Dr exactly; sales issues at the current avg cost — the SAME value the
    SINV journal books as COGS — so Stock value always reconciles to the GL Inventory balance.
  - **Default warehouse**: purchase bills and sales invoices carry NO `warehouse_id` column
    (deliberate scope cut) — their movements attach to the company's first active warehouse via
    `StockService::warehouseFor()` (`where is_active orderBy id value('id')`).
  - **Adjustments**: draft → posted; posting RECOMPUTES each line's `system_qty`/`quantity_delta`
    from the ledger at post time (`counted − system`, deltas of 0 are skipped and zeroed), and
    values at the current avg cost (cost ≤ 0 → `StockPostingException` — the product needs a
    purchase price or a receipt). Posting creates an `ADJ` journal (grouped per inventory account
    from product `inventory_account_id` → setting default; expense = account code **5182**
    "Inventory Adjustment Expense" seeded under 5180, fallback setting default purchase account;
    positive delta → Inventory Dr / Expense Cr, negative reverses both), requires postable-leafs
    and balanced lines (both verified server-side), then `numbering at posting`: `ADJ-{year}-%04d`,
    sets `total_value` = Σ abs(line_value), status `posted`, and records the movement(s).
  - **Transfers**: `from_warehouse_id ≠ to_warehouse_id` (request-level `Rule::notIn`),
    insufficient source on-hand → `StockPostingException`; posting records `transfer_out`
    (-qty, source wh) + `transfer_in` (+qty, dest wh) at current cost, and `TR-{year}-%04d`.
    NO journal — asset value is unchanged; posters simply verify the balance moved warehouses.
  - **Draft-only mutations**: edits/delete 422 on posted adjustments/transfers; re-post on a
    posted doc is a no-op redirect. Permissions (new `inventory` module already existed in
    `PermissionSeeder`): `inventory.view/create/update/delete/adjust/transfer`; RoleSeeder:
    accountant gained `inventory.*` (was view), `inventory-manager` already had `inventory.*`,
    viewer keeps `inventory.view`. Route bases chosen as `stock`, `stock-adjustments`,
    `stock-transfers` so the sidebar `isActive` base-match highlights exactly one item per area.
  - **Low-stock alerting**: `products.low_stock_threshold` (nullable decimal, form input hidden
    until "Track inventory" is checked) → `is_low_stock` = track_inventory && threshold !== null
    && on-hand ≤ threshold; Stock page red-highlights rows + shows a `≤ {threshold}` badge and a
    summary `low_stock_count`. **Testing gotcha**: the threshold input is `v-if`-gated on the
    track_inventory checkbox — a driver/evaluator must tick the checkbox BEFORE writing the input.
  - **UI**: `Pages/Inventory/Stock/Index.vue` (3 summary cards, warehouse filter merges search via
    `router.get(route('stock.index'), ...)`, links rows to `products.edit` — **there is no
    `products.show` route**, calling `route('products.show', id)` throws a Ziggy error at render),
    `Adjustments/{Index,Create,Edit,Show,AdjustmentForm}` (live system-vs-counted delta column;
    Show links to its `ADJ` journal), `Transfers/{Index,Create,Edit,Show,TransferForm}`.
    Sidebar "Inventory" group; breadcrumb labels `stock`/`stock-adjustments`/`stock-transfers`;
    `AppIcon` gained `stock`/`alert`/`adjust`/`transfer`; `Journals/Show` gained the
    `stock_adjustment` label + "View Adjustment" source link.
- **Phase 8 domains added (Receivables — AR workflow)**: `app/Domain/Receivables/` holds
  `ReceivableService`, `ReceivableController`, `ReceivablePaymentRequest`/`AdvanceReceiptRequest`/
  `AdvanceApplicationRequest`/`WriteOffRequest`, `ReceivablePostingException`. Scope: multi-invoice
  receipt allocations, advance receipts (Cash Dr | Customer Advances Cr) applied to invoices later
  (`RCA` journal), outstanding list, aging report (Current/1-30/31-60/61-90/90+), write-offs
  (Bad Debt Dr | AR Cr), and an AR dashboard. No credit notes/returns yet. Phase 9 (AP) mirrors it.
  - **Multi-alloc receipts**: one receipt row may allocate across N posted invoices of the same
    customer; journal = Cash/Bank Dr (total) | AR Cr (total), per-invoice `amount_paid`
    incremented. `RC-{year}-%04d` numbering shares the SAME sequence as Phase 5's single-payment
    `SalesInvoiceService::nextReceiptNumber` (both scan `receipts.receipt_no` — no collision).
    Server re-checks each allocation ≤ that invoice's live `balanceDue()`, posted status, and
    customer ownership; over-allocation/foreign-invoice → `ReceivablePostingException` → redirect.
  - **Advances**: a receipt with `type='advance'` (`ReceiptType` enum `receipt|advance`, new
    `receipts.type` column) books Cash Dr | **Customer Advances** Cr. The liability account is NOT a
    settings column — it's COA leaf code **2161** (seeded under 2160) via
    `ReceivableService::advanceAccountFor()`. Applying an advance posts a `receipt_application`
    journal (Customer Advances Dr | AR Cr) that reuses the `receipt_allocations` pivot, so BOTH the
    advance and its applications point at the same source_id; `Receipt::journal()` must match
    `whereIn('source_type', ['receipt','receipt_application'])`. `advanceBalance() = amount −
    Σ(allocation)`, and application is refused above it. New `JournalSourceType` cases were added:
    `ReceiptApplication` (label 'Advance Applied', prefix **RCA**) and `WriteOff` (label
    'Receivable Write-off', prefix **WOF**) — required because `nextJournalNumber()` calls
    `JournalSourceType::from($sourceType)` (§1.18).
  - **Write-offs**: `sales_invoices` gained `write_off_amount`/`write_off_reason`/`written_off_at`/
    `written_off_by`; `balanceDue()` = total − amount_paid − write_off_amount (min 0) and
    `paidState()` treats full write-off as 'paid'. Journal = **Bad Debt Expense (code 5191, seeded
    under 5190)** Dr | AR Cr, `source_type='write_off'` with `source_id` = the invoice, so
    `Journals/Show` links "View Invoice". Gated by `receivables.write_off` (approval-by-permission
    since the Phase 16 approval engine doesn't exist).
  - **Route-base naming** (sidebar §1.17 lesson): each Receivables sidebar item has a DISTINCT
    base — `receivables.index` (/receivables), `outstanding.index`, `aging.index`,
    `payment.index|store`, `advances.index|store|show|apply`, `receivable-write-off.store`
    (POST /receivables/invoices/{invoice}/write-off) — so `isActive` highlights exactly one item.
  - **Permissions**: new module `receivables` = `view|advance|write_off`; accountant gets
    `receivables.*`, sales-executive `receivables.view`+`receivables.advance`, viewer
    `receivables.view`. `receipt.post` is reused for multi-alloc payments + advance application.
    **Open item fixed**: `CompanyController::provisionDefaults()` did NOT actually grant roles to
    new companies (AGENTS claimed it did) — it now calls `(new RoleSeeder())->run($company->id)`
    so new-company role mappings exist; the DB seeder must be re-run (idempotent) for existing
    companies after adding role grants.
  - **UI**: `Pages/Receivables/{Index,Outstanding,Aging,RecordPayment}.vue` + `Advances/{Index,
    Show}.vue`. New advances are recorded via a modal on Advances/Index posting to `advances.store`
    (no GET create route — avoid a ghost page/breadcrumb). Record Payment has allocate-all/clear
    and per-invoice amount inputs; a Write Off modal lives on `Sales/Invoices/Show` (`#wo_amount`/
    `#wo_reason` — reason is a **textarea**, so a CDP driver must set the value with
    `HTMLTextAreaElement.prototype` setter, not `HTMLInputElement.prototype`). Sidebar "Receivables"
    group; breadcrumb labels `receivables`/`outstanding`/`aging`/`payment: 'Record Payment'`/
    `advances: 'Customer Advances'`; `Journals/Show` gained the RCA/WOF labels + "View Invoice".
  - **Gotcha — Collection indirect modification**: `agingRows()` must NOT do
    `$customers[$key][$bucket] = round(...)` — Collection's ArrayAccess returns a copy, PHP emits
    "Indirect modification of overloaded element has no effect", the write is silently dropped, and
    aging columns stay 0. Copy the row out, mutate the copy, and write `$customers[$key]` back.
  - **AR dashboard**: `total_receivable`, `overdue` (balance on invoices with `days_overdue > 0`),
    `collected_this_month` (receipt_allocations joined to receipts where `receipt_date >= first of
    month`, all receipt types), `advance_balance` (Σ unapplied), `top_customers` (top 5 by balance),
    `recent_receipts` (latest 8).
- **Phase 9 domains added (Payables — AP workflow)**: `app/Domain/Payables/` holds
  `PayableService`, `PayableController`, `MultiBillPaymentRequest`/`SupplierAdvanceRequest`/
  `SupplierAdvanceApplicationRequest`, `PayablePostingException`. Scope: the AP mirror of Phase 8 —
  multi-bill supplier payments, supplier advances (Cash Dr | Advances to Suppliers Cr) applied to
  bills later, outstanding list, AP aging report, and an AP dashboard. No AP write-off/debit-notes yet.
  - **Supplier payments (multi-alloc)**: `payables.record-payment` posts ONE `payment` (PMT) journal
    (AP Dr total | Cash/Bank Cr total) covering N posted bills of the same supplier; `PY-{year}-%04d`
    numbering SHARES the Phase 6 sequence (`PayableService::nextPaymentNumber()` scans
    `supplier_payments.payment_no`). Server re-checks each allocation ≤ that bill's live
    `balanceDue()`, posted status, and supplier ownership; over-allocation/foreign-bill → `PayablePostingException`.
  - **Advances**: receipt with `type='advance'` (`SupplierPaymentType` enum `payment|advance`, new
    `supplier_payments.type` column) books Cash/Bank Cr | **Advances to Suppliers** Dr. The asset
    account is NOT a settings column — it's a COA leaf code **1161** (seeded under 1160) via
    `PayableService::advanceAccountFor()`. Applying an advance posts a `payment_application` journal
    (AP Dr | 1161 Cr) that REUSES the `supplier_payment_allocations` pivot, so BOTH the advance and
    its applications point at the same `source_id`; `SupplierPayment::journal()` must match
    `whereIn('source_type', ['payment','payment_application'])`. `advanceBalance() = amount −
    Σ(allocation)`, application refused above it. New `JournalSourceType` case:
    `PaymentApplication` (label 'Advance Applied (Supplier)', prefix **SAA**) — required because
    `nextJournalNumber()` calls `JournalSourceType::from($sourceType)` (§1.18).
  - **Route-base naming** (sidebar §1.17 lesson): each Payables sidebar item has a DISTINCT base —
    `payables.index` (/payables), `payable-outstanding.index`, `payable-aging.index`,
    `supplier-payment.index|store` (/payables/record-payment), `supplier-advances.index|store|show|apply`
    (/payables/advances...) — so `isActive` highlights exactly one item.
  - **Permissions**: new module `payables` = `view|advance`; accountant gets `payables.*`,
    purchase-executive `payables.view`+`payables.advance`, viewer `payables.view`. `payment.post`
    is reused for multi-alloc payments + advance application.
  - **UI**: `Pages/Payables/{Index,Outstanding,Aging,RecordPayment}.vue` + `Advances/{Index,Show}.vue`.
    New advances are recorded via a modal on Advances/Index posting to `supplier-advances.store` (no
    GET create route). Record Payment has allocate-all/clear + per-bill amount inputs; the Advance
    Show has an "Apply to Bills" modal (`apply_{bill.id}` inputs). Sidebar "Payables" group; breadcrumb
    labels `payables`/`payable-outstanding`/`payable-aging`/`supplier-payment: 'Record Payment'`/
    `supplier-advances: 'Supplier Advances'`; `Journals/Show` gained the SAA label + "View Advance".
  - **Gotcha — §1.22**: `PayableController::supplierOptions()` was copied from the AR side and
    selected `credit_limit` (customer-only) from `suppliers` → passed all SQLite tests, 500'd in
    MySQL on /payables + /payables/outstanding + /payables/record-payment + /payables/advances.
    Mapped columns must be audited against the receiving table's migration.
  - **AP dashboard**: `total_payable`, `overdue` (balance on bills with `days_overdue > 0`),
    `paid_this_month` (Σ `supplier_payments.amount` for the month, all types — deliberately simpler
    than the AR allocations join), `advance_balance` (Σ unapplied), `top_suppliers` (top 5 by balance),
    `recent_payments` (latest 8).
- **Phase 6b domains added (Cash & Bank)**: `app/Domain/CashBank/` holds `CashAccount`, `BankAccount`,
  `CashBankTransaction`, `BankStatementImport`, `BankStatementLine`, `CashBankService`,
  `BankReconciliationService`, `CashBankController`/`CashBankAccountController`/
  `CashBankTransactionController`/`BankReconciliationController`, the form requests, and
  `CashBankPostingException`. Scope: direct cash receipts/payments, bank deposits/withdrawals,
  transfers, charges/interest, plus statement-CSV bank reconciliation that locks completed periods.
  - **Unified ledger**: ONE `cash_bank_transactions` table serves both cash and bank moves (superset of
    the architecture doc's `bank_transactions`) to avoid double rows for cash↔bank moves — a
    deliberate deviation from `accounting-erp-architecture.md` §19; each row targets its GL account via
    `cash_account_id`/`bank_account_id` (mapped through `gl_account_id`), plus `to_bank_account_id` and
    `counter_account_id`. Types via `CashBankTransactionType` enum (`cash_receipt|cash_payment|
    bank_deposit|bank_withdrawal|bank_transfer|bank_charge|bank_interest`).
  - **Posting**: every transaction is posted immediately as a balanced `Journal` (`source_type=bank`)
    — Cash Receipt: Cash Dr | Counter Cr; Cash Payment: Counter Dr | Cash Cr; Deposit: Bank Dr | Cash
    Cr; Withdrawal: Cash Dr | Bank Cr; Transfer: ToBank Dr | Bank Cr; Charge: Counter Dr | Bank Cr;
    Interest: Bank Dr | Counter Cr. Numbering at creation `CBT-{year}-%04d` (transaction_no, NOT
    journal_no which uses the shared `CBT` prefix). All GL accounts (cash/bank GL + counter) must be
    postable leaves with active cash/bank accounts (`CashBankPostingException` otherwise). The
    `CashBankTransactionRequest` enforces required account fields per type (incl. `different:bank_account_id`
    for transfers). **Vue gotcha**: the form selects are `v-if`-gated on the computed type — a
    driver must pick the type BEFORE filling accounts.
  - **Reconciliation**: `bank_statement_imports` (month + uploaded file) → `bank_statement_lines`
    (one per CSV row `date,description,amount`; parentheses/DR = negative; header row skipped).
    `BankReconciliationService::import()` auto-matches by exact date + magnitude, then `complete()`
    requires ALL lines matched and stamps `bank_account.last_reconciled_date = end-of-month`.
    `CashBankService::deleteTransaction()` refuses deletions of transactions dating **on/before the
    last reconciled date** (super admin exempt). `statement_month` accepts `YYYY-MM` (browser month
    input) — the request validates with `regex:/^\d{4}-\d{1,2}(-\d{1,2})?$/` and the service
    normalises via `monthStart()`.
  - **Permissions/UI**: reuses the pre-seeded `bank` module (`view|create|update|delete|reconcile`) —
    accountant has `bank.*`, viewer `bank.view`. Route base `cash-bank` (index/transactions/accounts/
    reconciliations/reconciliations.show + sub-actions). `CashBankTabs` sub-navigation (Overview/
    Transactions/Accounts/Reconciliation); sidebar "Cash & Bank" group; breadcrumb label
    `cash-bank: 'Cash & Bank'`. `AppIcon` gained `bank`/`expense`.
- **Phase 6b added Expense (module 19)**: `app/Domain/Expense/` holds `ExpenseCategory` (+table
  `expense_categories`: name + default `expense_account_id`) and `Expense` (+table `expenses`),
  `ExpenseService`, `ExpenseController`/`ExpenseCategoryController`, `ExpenseRequest`/`ExpenseCategoryRequest`,
  `ExpensePostingException`, and the `expenses:generate-recurring` console command. Scope: expense
  categories (modal CRUD on `Expense/Categories.vue`), expenses drafted under a category
  (draft → posted `EXP` journal) with `ExpensePaymentMethod` (`cash|bank|payable`), and recurring expenses
  that auto-generate the next DRAFT copy on schedule (never auto-posted).
  - **Journal at posting**: `Expense Account Dr (net) | Input Tax Dr (tax, via tax_rate.input_account_id →
    setting default) | credit = total` to `cash_account.gl_account_id` (cash), `bank_account.gl_account_id`
    (bank), or `supplier.ap_account_id → setting default_ap_account_id` (payable, party_type=supplier).
    Expense account = `category.expense_account_id`; everything must be a postable leaf
    (`ExpensePostingException` otherwise). `expense_no = EXP-{year}-%04d` numbering is assigned only at
    posting (drafts have null); journal `source_type=expense` (prefix `EXP`).
  - **Forms**: method selects are `v-if`-gated (`#expense_cash`/`#expense_bank`/`#expense_supplier`) — a
    driver picks the payment method first. Category/payee/date/amount + optional tax (server recomputes
    `tax_amount = amount × rate%` from the stored amount). Recurring toggle adds frequency
    (weekly/monthly/yearly) + optional next-run.
  - **Recurring**: `is_recurring` + `recurrence_frequency` + `next_generation_date`; the command finds
    posted recurring expenses with `next_generation_date <= today`, creates a DRAFT copy (expense_date =
    next run, notes prefixed "Recurring copy from EXP-…"), and advances BOTH the copy's and the source's
    schedule. Register nothing in `routes/console.php` — Laravel 11 auto-discovers `app/Console/Commands/`.
  - **Permissions/UI**: reuses pre-seeded `expense` module (`view|create|update|delete|approve|post`);
    accountant `expense.*`, viewer `expense.view`. Route base `expenses` (+ `expense-categories` route
    base for the category page). **Ordering lesson**: the static `/expenses/categories` routes MUST be
    registered BEFORE `/expenses/{expense}` (`expenses.show`) or `{expense}` swallows "categories" → 404.
    Sidebar "Expenses" group (separate heading below Cash & Bank) + breadcrumb labels
    `expenses: 'Expenses'`/`expense-categories: 'Expense Categories'`/`expenses: 'New Expense'`.
    `ExpenseCategorySeeder` seeds Rent/Utilities/Salaries/Office Supplies/Travel → COA leaves
    5121/5131/5111/5141/5142 (wired into `DatabaseSeeder` + `provisionDefaults`);
    in-use categories deactivate instead of delete.
- **Phase 7a domain added (Fixed Assets — module 21)**: `app/Domain/FixedAsset/` holds
  `AssetCategory` (+table `asset_categories`), `FixedAsset` (+table `fixed_assets`),
  `DepreciationEntry` (+table `depreciation_entries`, unique `(fixed_asset_id, period_id)`),
  `AssetDisposal` (+table `asset_disposals`), `FixedAssetService`,
  `FixedAssetController`/`AssetCategoryController`, the form requests
  (`FixedAssetRequest`/`AssetCategoryRequest`/`FixedAssetDisposeRequest`/`DepreciationRunRequest`),
  `FixedAssetPostingException`, and enums `DepreciationMethod` (`straight_line|declining_balance`),
  `AssetStatus` (`draft|active|disposed`), `AssetPaymentMethod` (`cash|bank|payable`).
  Scope: asset register with categories (one set of asset/accum-dep/dep-expense accounts + default
  method/life per category), capitalize-on-posting, scheduled depreciation (batch, per open period),
  and disposal. No asset revaluation/impairment/partial-disposal yet.
  - **Lifecycle**: drafts are register-only (edit/delete allowed); `capitalize()` posts the acquisition
    journal (`source_type=capitalization`, prefix **FA**, number `FA-{year}-%04d`) and flips
    `status=active`. `runDepreciation()` posts ONE batch DEP journal per period (`source_type=depreciation`,
    prefix **DEP**) with 2 lines per active asset **not already booked** for that period (idempotent via the
    `(fixed_asset_id, period_id)` unique key) — Dep Expense Dr | Accumulated Dep Cr; amounts use the asset's
    current method: SL = cost/life, DB = book × (2/life) capped at book. `dispose()` posts
    `source_type=asset_disposal` (prefix **DSP**): Cash Dr (proceeds) + Accumulated Dep Dr | Fixed Asset Cr
    (cost) + Gain Cr / Loss Dr; requires an **active** asset; proceeds cash account defaults to the first
    active cash account; gain/loss auto-derived (postable-leaf Gauss/Loss accounts MUST exist).
  - **Don't forget**: each journal `source_type` needs a `JournalSourceType` case (label + prefix) because
    `nextJournalNumber()` calls `JournalSourceType::from($sourceType)` (§1.18). Added
    `capitalization` ('Asset Acquisition', FA) and `asset_disposal` ('Asset Disposal', DSP);
    `depreciation` (DEP) already existed.
  - **The `journal_date` for the DEP batch = `period->end_date`**; acquisition journal date = acquisition
    date. **Route model binding**: route param name MUST equal the controller method's variable name —
    routes were written as `/fixed-assets/{fixed_asset}` while the controller typed `FixedAsset $asset`;
    implicit binding fails so the arg resolves to a fresh empty model and every capitalized/dispose/dep
    POST 404s in the SPA. Use `{asset}` when the arg is `$asset` (this bit every `POST /fixed-assets/{id}/…`
    sub-action; the standalone `/{fixed_asset}` word-vs-arg mismatch is a silent 404 trap).
  - **Vue gotcha (unrelated but hit in nav)**: asserting rendered text via `document.body.innerText` misses
    text inside certain overflow/transitioning containers, while the same string IS in `textContent` and
    `outerHTML` — for SPA content assertions prefer `textContent` over `innerText` in the headless pass.
  - **DepreciationRunRequest** validates `period_id` with a plain `exists:accounting_periods,id` rule —
    `Rule::exists(...)->where(fn)`, `->whereHas(...)`, and closures in `where()` all blow up
    (`str_replace(): Argument #3 ($subject) must be of type array|string, Closure given` / unknown
    `whereHas` on `Exists`). The service scopes the period by company instead.
  - **Permissions**: `fixed_asset` module (`view|create|update|delete|depreciate`) was already in
    `PermissionSeeder`; RoleSeeder grants accountant `fixed_asset.*`, viewer `fixed_asset.view`.
    Route map: create register+category store; update edit/capitalize; delete draft-destroy/dispose/category
    delete; depreciate run-depreciation. `Store` returns `redirect()->route('fixed-assets.show', $asset)`.
  - **COA/seeders**: `ChartOfAccountsSeeder` gained postable leaves **4213 Gain on Disposal of Fixed
    Assets** (under 4210) and **5193 Loss on Disposal of Fixed Assets** (under 5190).
    `AssetCategorySeeder` seeds Buildings & Structures (1212/1221/5151/SL/240), Machinery & Equipment
    (1213/1222/5151/SL/60), Furniture, Fixtures & Computers (1214/1223/5151/SL/36), Vehicles
    (1215/1224/5151/SL/60) — wired into `DatabaseSeeder` + `provisionDefaults`.
  - **UI**: `Pages/FixedAssets/{Index,Create,Edit,Show,Categories,AssetForm}.vue`. Index has a
    `Run Depreciation` bar (`#dep_period`) posting `fixed-assets.depreciate` + status/category filters.
    Show links to the FA/asset journal (`Journals/Show.vue` gained `capitalization|depreciation|
    asset_disposal` labels + "View Asset" source links); Show computes accumulated/book value client-side
    and renders a future depreciation schedule. AppIcon gained `asset`; StatusBadge gained `disposed`.
    Breadcrumbs `fixed-assets: 'Fixed Assets'`/`asset-categories: 'Asset Categories'`.
  - **Test gotchas in Phase7aCoreTest**: helpers that persist assets DIRECTLY must include `company_id`
    (CREATE doesn't inherit it); posting-only aspirations are NOT satisfied by
    capitalizing + then asserting the draft is postable — exercise the real endpoints. `DepreciationRun
    POST` returns a flash `error` (not a field error) when the service rejects.
- **Phase 7b domain added (Payroll — module 22)**: `app/Domain/Payroll/` holds `Department`,
  `Designation`, `Employee`, `SalaryStructure`, `PayrollRun` (+`PayrollRunLine`), `SalaryPayment`,
  `PayrollService`, `EmployeeController`/`DepartmentController`/`DesignationController`/
  `PayrollRunController`, the form requests, `PayrollPostingException`, and
  `app/Support/Enums/SalaryPaymentMethod` (`cash|bank`). Scope: employee register with a salary
  structure (1:1 child table), process a payroll run per period (draft → review → post accrual →
  pay salaries). Attendance/leaves are DEFERRED (documented future scope) and there is **no**
  `attendances`/`leaves` table.
  - **Tables**: `departments`, `designations` (both `is_active`, unique name per company),
    `employees` (+`company_id`, `department_id`, `designation_id`, `join_date`, `is_active`),
    `salary_structures` (**1:1 child of employee** — deliberate deviation from the arch doc's JSON
    column: FIXED columns `basic`, `house_rent_allowance`, `medical_allowance`, `travel_allowance`,
    `other_allowance`, `income_tax_deduction`, `provident_fund_deduction`, `other_deduction`;
    gross = basic + allowances, net = gross − deductions, computed in `SalaryStructure`),
    `payroll_runs` (unique `(company_id, period_id)` per company, one run per period),
    `payroll_run_lines` (unique `(company_id, payroll_run_id, employee_id)`), `salary_payments`.
  - **Lifecycle**: `process` creates a DRAFT run dated the period's `end_date`, one line per ACTIVE
    employee that has a salary structure (service filters, refused if none). Lines are adjustable
    while draft (gross/deductions; net and run totals recomputed server-side — §1.16 numeric
    normalization in `prepareForValidation`). `post` books the accrual journal
    (`source_type=payroll`, prefix **PYR**): **Salary Expense (5111) Dr (gross) | Salary Payable
    (2141) Cr (net) | Employee Deductions Payable (2143) Cr (deductions)** — 2143 is a leaf newly
    seeded under 2140 by `ChartOfAccountsSeeder`; only drafted when deductions > 0. `run_no =
    PR-{year}-%04d` assigned at posting (§1.18). Salary payment (`source_type=payroll` again, same
    PYR prefix) books **Salary Payable Dr | Cash/Bank GL Cr**, reuses `payroll_runs.period_id`/`run`
    reference, requires a POSTED run and `amount ≤ remainingPayable()` (overpayment refused),
    `payment_no = SP-{year}-%04d`. Both source types point `source_id` at `payroll_runs.id` so
    `Journals/Show` shows one "View Payroll Run" link for accrual and payment alike.
  - **Don't forget (§1.18)**: `PayrollRun`/`SalaryPayment` journals need `JournalSourceType::Payroll`
    (label 'Payroll', prefix PYR) — already existed. Numbering helpers scan their own tables
    (`nextRunNo`/`nextPaymentNo`) for the max `run_no`/`payment_no`; the PYR journal number comes
    from `JournalPostingService::nextJournalNumber()` shared sequence user.
  - **Route model binding gotcha (again)**: a controller method MUST declare route params in the SAME
    ORDER as the route. `PUT /payroll/runs/{run}/lines/{line}` with method `updateLine($run, $line)`
    works, but `updateLine($line)` alone 500s with
    `Argument #1 ($line) must be of type PayrollRunLine, string given` — Laravel's implicit binding
    `array_shift`s from the route-parameter list in ROUTE order, so `$line` receives `{run}`'s value
    when `$run` isn't declared. Always declare `($run, $line)` (or dance around §7a's word-vs-arg 404
    trap by keeping every param).
  - **Permissions**: `payroll` module (`view|create|update|delete|process|post`) was already in
    `PermissionSeeder`. RoleSeeder grants accountant `payroll.view` + **`payroll.post` only** (NOT
    `payroll.*` — accountants review/post but the HR-Payroll manager processes), viewer `payroll.view`.
    Route map: index/show = view; process = process; runs/employees update/edit = update; delete =
    delete; post/payments.store = post; department/designation store = create.
  - **Sub-resource delete = deactivate-when-in-use** (mirrors Expense categories): a department or
    designation assigned to employees is set `is_active=false` (kept, `info` flash) instead of
    deleted; only unused ones hard-delete (`assertSoftDeleted` in tests — the row persists because of
    SoftDeletes).
  - **UI**: single sidebar "Payroll" item (base `payroll`); `Components/PayrollTabs.vue` (Runs |
    Employees) mirrors `CashBankTabs`. `Pages/Payroll/{Index, Employees, EmployeesCreate,
    EmployeesEdit, EmployeeShow, RunShow}.vue`. Index = "Process Payroll" bar (`#pr_period` POST
    `payroll.process`) + status filter. Employees embeds departments + designations as two
    inline-edit modals (Add row + per-row Edit/Save/Delete). RunShow: edit-in-place gross/deductions
    per line with live net + totals footer (`#adjust` → Save → `payroll.runs.lines.update`),
    Post/Delete on drafts, "Record Salary Payment" modal (`#pay_method` select, method-gated
    `#pay_cash`/`#pay_bank`, `#pay_amount` prefilled with remaining, `#pay_date`); payments table.
    Breadcrumb `payroll: 'Payroll'` (module) + `'Employee'` (singular) + `'New Employee'` (create);
    `AppIcon` gained `payroll`; `Journals/Show` gained the `payroll` label + "View Payroll Run".
  - **Test gotchas in Phase7bCoreTest**: payroll runs reuse the seeded corporate demo company — keep
    every email unique; salary math must include ALL allowances/deductions from the base
    `employeePayload()` when overriding a subset (overwriting just `basic` while keeping the $5,000
    medical/travel allowances silently inflates gross by $10k — assertions must sum the full
    structure). The accountant positive test must create the draft run AS ADMIN first (accountant
    lacks `payroll.process`), then switch users to exercise `payroll.post`.
- **Phase 7c domain added (Budget — module 23)**: `app/Domain/Budget/` holds `Budget`
  (+table `budgets`) and `BudgetLine` (+table `budget_lines`), `BudgetService`,
  `BudgetController`, `BudgetRequest`, `BudgetPostingException`, and `app/Support/Enums/BudgetStatus`
  (`draft|posted`). Scope: one annual operating budget per company+fiscal year, per-account/per-period
  line amounts, and a budget-vs-actual variance report. **No journals are created by budgeting** — it is
  a planning record.
  - **Uniqueness**: `budgets` unique on `(company_id, fiscal_year_id)` — at most ONE budget per FY
    (`BudgetService::assertNoBudgetForFiscalYear`, raised on store AND update). `budget_lines` unique on
    `(budget_id, account_id, period_id)`; `BudgetService::assertPayload` also rejects, at service level,
    a second line for the same (account, period), a period OUTSIDE the budget's FY, and any account that
    is not a **postable income/expense leaf** (asset/liability/equity accounts are refused; `is_postable`
    + `type in [income, expense]` via `AccountType`).
  - **syncLines replaces wholesale on every draft edit**: `$budget->lines()->forceDelete()` then
    re-insert — plain `delete()` (soft) leaves the `(budget_id, account_id, period_id)` unique rows
    occupied in BOTH SQLite and MySQL → `UNIQUE constraint failed budget_lines.budget_id...` on the next
    PUT. `destroyBudget()` also `forceDelete()`s lines before soft-deleting the budget (¶1.16-style; lines
    are child rows, not auditable records).
  - **Posting** (`postBudget`) only flips `status=posted` (throws if already posted or zero lines);
    a posted budget is LOCKED: edit (GET/PUT) and delete are refused (`Only draft budgets can be edited.`).
    The controller's `edit()` can return `RedirectResponse` — declare `Response|RedirectResponse` or an
    already-posted budget 500s with a PHP TypeError.
  - **Variance** (`BudgetService::variance`): `actual` per account = balance of POSTED journal lines in
    the FY date range (`Journal.status=posted` + `journal_date between FY start/end`), signed by
    `normal_balance` (income → credit−debit, expense → debit−credit). Draft journals are excluded.
    Per-period actual reuses the journal's `period_id`. Returns `{periods, rows (per account: budgeted/
    actual/variance/variance_pct), detail (per budget line: period budgeted + actual)}`.
  - **Permissions/UI**: `PermissionSeeder` budget module = `view|create|update|delete|approve|post`
    (`budget.post` added for 7c); RoleSeeder accountant gains `budget.*`, viewer keeps `budget.view`.
    Route base `budgets`; index status filter; create/edit via `Pages/Budget/BudgetForm.vue` (shared:
    name + fiscal-year select — DISABLED on edit since FY is immutable — + line editor; the
    period `<select>` is client-filtered to the chosen FY's periods). Show = 4 summary cards
    (Status/Budgeted/Actual YTD/Variance) + per-account variance table + per-period detail table,
    `Post Budget`/`Edit`/`Delete` for drafts. Sidebar label is **"Budgets"** (plural — the §1.17-style
    nav script must search the exact label); `AppIcon` gained `budget`; breadcrumbs
    `budgets: 'Budgets'`/singular `Budget`/create `New Budget`. `BudgetRequest` normalizes
    `lines[].budgeted_amount` null → `'0'` (§1.16).
  - **Permission note**: budget lines/variance pages render for viewer too (`budget.view`); only
    mutations are gated.
- **Phase 8a domain added (Accounting Reports — module 24)**: `app/Domain/Report/` holds just
  `ReportService` + `ReportController` — NO new tables (reports are read-only query layers over
  `journal_line`/`journal` per the arch doc's "not modules with source-of-truth tables" rule).
  `Pages/Report/Index.vue` renders BOTH reports behind tab buttons (General Ledger | Trial Balance)
  that re-issue `router.get(route('reports.index'), {report, fiscal_year_id, period_id/account_id})`.
  - **Range resolution**: `ReportService::filters()` picks the most-recent fiscal year when none is
    chosen, then a single period if `period_id` belongs to it, else the whole FY. `from`/`to` fall
    back to the FY dates. **Gotcha**: the `->get(['id','name','fiscal_year_id', ...])` column list
    MUST include `start_date`/`end_date` on the periods select or `$period->start_date` is null →
    `Call to a member function toDateString() on null` on the very first period-filtered request
    (plain /reports passes because a null period falls through to the FY dates — only the period
    path blows up). TDD caught this, not the nav pass.
  - **GL running balance** carries the account's balance from the FY start into the report window
    (`priorBalances()` sums posted lines from `fy.start_date` to `from − 1 day`) and per-account
    `summary` (debit/credit/net/closing) + report-wide `totals`. Entries sorted by journal_date then
    journal_id; the Vue page groups rows under account subheader rows client-side (`ledgerRows`
    computed mixing `{kind:'header'}` + `{kind:'entry'}` — never render `<template v-for>` with a
    stray empty `<tr>` + invented props like `$nextIndex`).
  - **Trial balance** is classic two-column: each account's NET balance goes on its normal side
    (asset/expense → Debit column, where a credit-heavy asset shows a NEGATIVE debit; liability/
    equity/income → Credit column); `balanced` = |debit − credit| < 0.01 (the arch doc's "trial
    balance sums to zero" invariant); footer totals row. Draft journals are excluded (status=posted
    filter only) — asserted in both GL and TB.
  - **Permissions/UI**: reuses the pre-seeded `report` module (`view|export`; accountant `report.*`,
    viewer `report.view`) — no seeder change. Route base `reports` → GET `/reports` only
    (`reports.index`, `permission:report.view`). Sidebar gained a **Reporting** group (label
    `Reports`, icon `report` added to `AppIcon`) placed after Budget, before Transactions (later
    phases add Statements + the real Dashboard to this group). Breadcrumb label `reports: 'Reports'`.
    `ReportController::index()` switches on `?report=general-ledger|trial-balance` (default GL) and
    merges only the matching payload — the Vue props diverge by tab (`filters`/`accounts`/`entries`/
    `summary`/`totals` vs `filters`/`rows`/`totals`/`balanced`), so the component props must be
    optional and the template branches on the `report` prop.
- **Phase 8b domain added (Financial Statements — module 25)**: `StatementService` +
  `StatementController` in the SAME `app/Domain/Report/` (no tables; another query layer per the
  arch doc). `Pages/Statements/Index.vue` renders four statements behind tab buttons that re-issue
  `router.get(route('statements.index'), {statement, fiscal_year_id, period_id})` sharing the
  ReportService range-resolution (`$this->reportService->filters()`).
  - **Shared tree machinery** (`flattenTree()/walk()/leafRow()`): loads all accounts of the given
    `type`s ordered by code, builds a `children` map keyed by `(int) parent_id` (NULL parents land
    on key 0), walks from `accounts->first()->parent_id`, and emits a flat row list mixing group rows
    (parent totals ROLLED UP from postable leaves only) + leaf rows with a `level` indent.
    `is_group`/`is_total` rows drive the shaded headers; `type` on each row is used to split section
    totals. Leaves keep `$balance[$account->id]` lookups — balances come from a single
    `SUM(debit)/SUM(credit)` query over POSTED lines in the range, signed by `normal_balance`.
  - **Income statement** returns Current + Year-to-date columns: Current = selected window
    (period or FY), YTD = `fy.start_date → window end`. Per-section "Total Income/Total Expenses"
    synthetic rows + `totals.net_income` (income − expense). Note the seeded COA has NO parent debt
    for 4000/5000 — every parent shows its Σ-leaf total, so a $0 "Total Revenue" section header is
    normal when only a single leaf (e.g. 4111) holds activity.
  - **Balance sheet** is cumulative `fy.start_date → as-of (window end)`, three tree sections
    (Assets/Liabilities/Equity). Equity gets a `synthetic = true` "Current Year Earnings" row
    (= net income for the same range) so `totals.liabilities_equity` reconciles to `totals.assets`;
    the page renders `totals.difference` (must be ±0.01).
  - **Cash flow (direct, no activity tags)**: cash accounts = postable asset leaves with codes
    `111%`/`112%`. For every posted journal touching cash in the window, the counterpart lines
    (non-cash) are classified by ACCOUNT CODE/TYPE — income/expense, current asset (≠12xx),
    current liability (≠22xx/215x) → operating; non-current asset (12xx) → investing;
    long-term liability (22xx)/short-term debt (215x)/equity → financing. Category net =
    −Σ(counterpart debit−credit); `reconciled` checks `opening + net_change === closing`
    (opening/closing = signed Σ cash accounts from FY start). This works for the seeded COA; any
    COA that books cash against exotic accounts should reassess `classify()`.
  - **Statement of equity**: per-equity-account Opening (FY start → window start − 1) / Movement /
    Closing (→ window end) + synthetic Current Year Earnings row + totals (movement = closing −
    opening).
  - **UI/test gotchas**: `vue-tsc` rejects inline `as T[]` type assertions inside template
    expressions (line 263 error `TS1005 ':' expected`) — hoist such literals to a script const
    (`bsSections`). New page files MUST be `npm run build`-ed before `php artisan test` — PHPUnit
    renders the real blade view and errors `Unable to locate file in Vite manifest:
    .../Pages/Statements/Index.vue` (500) until the page is in `public/build/manifest.json`, which is
    why Phase tests are run AFTER build. Cash & Bank group roll-up equals the Σ of its leaves
    (assert `round($leafSum,4)` against the group, no sign flip).
  - **Permissions/UI**: reuses `report.view` (accountant `report.*`, viewer `report.view`) — no
    seeder change. Route base `statements` → GET `/statements` only (`statements.index`). Sidebar
    Reporting group gained a **Statements** item (icon `statement` added to `AppIcon`, below
    Reports); breadcrumb `statements: 'Statements'`.
- **Phase 8c domain added (Dashboard — module 30)**: `DashboardService` joined `app/Domain/Report/`
  (read-only query layer — no tables) and `DashboardController` (app/Http) now renders a metrics
  landing page instead of the setup-modules skeleton. `Pages/Dashboard.vue` shows the hero
  (company/basis/FY/active period) + 6 KPI cards + `Trial Balance` health card + AR/AP open-item
  card + Reporting quick links + `Recent Journals` (latest 6 posted) + the existing multi-company
  strip. Route `dashboard` stays permission-free (any authenticated user).
  - **Metric ranges**: `activity` (income/expense/net) + the trial-balance check use the ACTIVE
    period's date range (fall back to the active fiscal year); `cash_balance` is cumulative UP TO
    `range.to` (no `from` — pass `null` `$from` to `signedSum`, which early-dates with
    `0000-01-01`). AR/AP figures come from `SalesInvoice::balanceDue()` / `PurchaseBill::balanceDue()`
    over POSTED rows (open count + overdue split by `due_date < today`).
  - **Gotchas hit in tests**: the test must post journals in the **active** period (not merely the
    first *open* period) or the range-scoped income/expense totals come back 0 while cash stays
    non-zero; and skipping the `whereHas('journal')` status filter (guarding it with `when($from)`)
    silently includes DRAFT lines in the cumulative cash balance — the status filter must ALWAYS
    apply (`status=posted` + `whereBetween` with an `0000-01-01` floor). `recent_journals` shows only
    posted rows.
  - **Permissions**: no seeder change (`dashboard` route is public to authenticated users; sidebar
    item already existed with `permission: null`). No migration.
- **Phase 9a domain added (Approval Engine — module 27)**: `app/Domain/Approval/` holds
  `ApprovalWorkflow` (+table `approval_workflows`: per-company rules with `module`, `min_amount`/
  `max_amount` band, `approver_role_id`/`approver_user_id`, `sequence`, `is_active`) and
  `ApprovalRequest` (+table `approval_requests`, the §2.4 polymorphic pair: `morphs approvable`,
  `requested_by`, `status pending|approved|rejected`, `current_step`/`total_steps`, `decided_by`/
  `decided_at`). `ApprovalWorkflowService` matches an amount-module band → ordered chain, and gating
  is **optional-by-default**: no matching active rule = documents post exactly as before.
  - **Posting gate**: `submitForApproval()` runs at the TOP of the `post()` methods of
    SalesInvoiceController, PurchaseBillController, ExpenseController, and JournalController (modules
    `sales_invoice|purchase_bill|expense|journal`, amounts = invoice/bill/expense total, journal debit
    sum). A matching unapproved rule makes post() redirect-back-with-error and creates/returns a
    PENDING request; an existing APPROVED request (or no rule) lets posting proceed; REJECTED →
    the next submit creates a fresh request. `approve()` advances `current_step` (multi-sequence
    chains need every step's approver before `status=approved`); `reject()` stores `reject_reason`.
    `approverCan()` = super-admin bypass OR user holds the step's `approver_role_id` (pivot
    `company_id` scoped) OR IS the configured `approver_user_id` — anything else throws
    `ApprovalException` → back()->with('error').
  - **Permissions/UI**: new `approval` module = `view|approve|configure` in `PermissionSeeder`;
    RoleSeeder: accountant gains `approval.view`+`approval.approve`, viewer `approval.view`
    (company-admin keeps `*`). Route base `approvals` (index + approve/reject) and
    `approval-workflows` (index + store/update/destroy, mutations gated `approval.configure`).
    Sidebar **Governance** group (Approvals + Approval Workflows); breadcrumbs `Approval Workflows`;
    `AppIcon` gained `approval`/`workflow`. Pages: `Approval/Index.vue` (pending table with
    Approve/Reject-per-approver buttons + recent-decisions table, reason textarea in a reject Modal —
    reuse the `HTMLTextAreaElement` setter lesson if driving it via CDP) and `Approval/Workflows.vue`
    (modal CRUD like Currencies; `sequence`/`min_amount` stay client-side STRINGS — vue-tsc rejects
    `v-model` on a `number` into `TextInput`, feed `String(w.sequence)` on edit; server normalizes).
  - **Tests (Phase9aCoreTest, 9 tests)**: gate-then-approve-then-post flow, rejected→resubmit creates
    a NEW request (`requestFor()` helper must use `latest('id')` — `firstOrFail` picks the stale
    rejected row), multi-step chain advances step-by-step, below-threshold posts without a request,
    viewer can view but `POST approve` 403s, configure permission enforced (360-nav green 12/12).
- **Phase 9b domain added (Audit UI — module 26)**: `AuditController` in the EXISTING
  `app/Domain/Audit/` (tables + `AuditLogger` existed from Phase 1/2; this phase renders the
  append-only log). Route `GET /audit` (`audit.index`, `permission:audit.view`); `Pages/Audit/Index.vue`
  with module/action/date/free-text filters (`router.get(route('audit.index'), {preserveState})`),
  dense table (When/User/Module/Action/Record/IP/Changes) + a details Modal with a per-field
  Before/After diff AND raw old/new JSON `<pre>` blocks.
  - **Server-side diff** (`AuditController::diff()`): compares `old_values`/`new_values` arrays,
    drops `created_at/updated_at/deleted_at`, emits up to 8 `{field, old, new}` rows (values shortened
    to 120 chars); the modal still needs the RAW `old_values`/`new_values` in the Inertia prop too —
    the page renders `json(row).old/new`. Without the raw arrays the modal JSON would be empty.
  - **Ordering gotcha in tests**: `logs` list is `orderByDesc('id')` — the row expected at
    `logs.data.1` in a test is NOT the second *created* entry; data is newest-first, so assert
    against the correct index (the customer-update sample ends at index 2 after 4 seeded logs).
  - **Permissions/UI**: no seeder or migration change (`audit.view` already granted to accountant +
    viewer). Sidebar **Governance** gained `Audit Log` (base `audit`, active-state unique);
    breadcrumb `audit: 'Audit Log'`; `AppIcon` gained `audit` (magnifier). Tests (Phase9bCoreTest,
    5 tests) cover render + module/action filters + diff shape + viewer-allowed/plain-user-403.
- **Phase 9c domain added (Notifications — module 28)**: `app/Domain/Notification/` holds
  `Notifications/{DocumentNeedsApproval,ApprovalDecision}.php` (database channel; `data` carries
  `company_id`, `category`, `title`, `body`, `approvals_url`) and `Services/NotificationService.php`
  (`notifyApprovers()` / `notifyRequester()`). Uses Laravel's stock `notifications` table via the
  `Notifiable` trait's `HasDatabaseNotifications` — migration `2026_10_06_100011_create_notifications_table.php`.
  No `Notification` model is needed below `DatabaseNotification` (type-hint it in controllers for
  route-model binding). Scope by `data->company_id` (JSON column) on every read.
  - **Wiring**: `ApprovalWorkflowService::submitForApproval()` notifies approvers after creating a
    PENDING request; `approve()` notifies the requester ONLY when the chain reaches final `approved`;
    `reject()` always notifies. Read paths are personal+company-scoped: `read()` 403s unless the
    notification belongs to the acting user, `readAll()` marks only rows whose `company_id` data
    matches the active company.
  - **§1.21 redux — never `wherePivot` inside `whereHas`**: `User::whereHas('roles', fn ($q) =>
    $q->where('roles.id', $id)->wherePivot('company_id', $cid))` silently selects ZERO users (the
    constraint builder offers no pivot) → approvers never get notified while all tests for the
    posting gate stay green. The pivot table `user_roles` is a plain table — query it directly:
    `UserRole::where('role_id', $id)->where('company_id', $cid)->pluck('user_id')` and merge.
  - **Permissions/UI**: no permission change (notifications are personal). Routes `notifications.index|
    read|read-all` (no permission gate — any authenticated user). Topbar **bell** (`AppIcon` `bell`)
    with an unread-count badge (9+ cap) links to the index; Sidebar **Governance** gained
    `Notifications`; breadcrumb `notifications: 'Notifications'`; shared prop
    `notifications.unread_count` added to `PageProps` (`resources/js/types/index.d.ts`).
    `Pages/Notifications/Index.vue` lists company-scoped rows (unread dot, category `StatusBadge`,
    click-to-read, Mark all as read). Tests (Phase9cCoreTest, 5 tests) cover approver/requester
    notice, page render + unread count, read, and company-scoped read-all.
- **Phase 9d domain added (Documents/Attachments — module 29)**: `app/Domain/Document/` holds
  `Attachment` (+ `attachments` table: the §2.4 polymorphic trio — `attachable_type/attachable_id`)
  with `file_path`/`original_name`/`mime_type`/`file_size`/`uploaded_by`, and the three-row
  `(company_id, attachable_type, attachable_id)` scoping, soft delete, audit-logged), the
  per-model `attachments()` morphMany on SalesInvoice/PurchaseBill/Expense/Journal,
  `AttachmentService`, `AttachmentController`, and `AttachmentStoreRequest`. Files land on a
  dedicated non-public **`uploads`** disk (`config/filesystems.php` → `storage/app/uploads/`),
  served only through `attachments.preview` (inline) / `attachments.download` routes; never in
  `public/`.
  - **Whitelist + gating**: `Attachment::ALLOWED_TYPES` maps `model => permission-slug`
    (`sales.update|purchase.update|expense.update|journal.update`). Store routes add a
    `permission:` middleware AND `AttachmentService::canUpload()` double-checks the attachable
    belongs to the **active company** (super admins included — a company mismatch 403s before the
    bypass) plus the module permission. `canAccess()` gates preview/download/destroy (super-admin
    bypass, company match, owning module's permission). Store/destroy audit-log under module
    `document`, actions `attach|detach`.
  - **Routes**: per-module POST `…/attachments` (param name MUST equal the controller method arg:
    `{invoice}`↔`$invoice`, `{bill}`↔`$bill`, `{expense}`↔`$expense`, `{journal}`↔`$journal` —
    §7a rule) + generic `attachments.preview|download` (GET) and `attachments.destroy` (DELETE).
    No permission middleware on the generic routes — access re-checked in the controller.
  - **UI**: reusable `Components/DocumentAttachments.vue` (list rows with preview/download/remove,
    upload via `router.post(..., { forceFormData: true })` on a hidden file input, size+uploader+
    date meta, `permission:` prop drives the attach/remove affordance via `usePage`); mounted on
    `Sales/Invoices/Show`, `Purchase/Bills/Show`, and `Journals/Show` (controllers serialize
    `attachments.uploader:id,name` through `AttachmentService::serialize()`). Journal Show now
    maps the raw model to `$journal->toArray()` + overwritten `attachments` key.
  - **Testing gotchas**: `Storage::fake('uploads')` must run in EVERY test (the disk is real
    otherwise); `assertDatabaseCount('attachments', …)` counts SOFT-DELETED rows too — use
    `assertSoftDeleted`; `accounting_periods` has NO `company_id` column (§1.22-style) — resolve a
    period via `whereHas('fiscalYear', fn ($q) => $q->where('company_id', …))`; cross-company tests
    must CREATE Company 2 first (the `users.company_id`/`sales_invoices.company_id` FKs reject
    inserts to a nonexistent company). Tests (Phase9dCoreTest, 7 tests) cover attach+show-list,
    journal attach+preview, download filename, destroy (row + file), viewer-403, cross-company
    upload 403, cross-company download 403.
- **Phase 10a domain hardening (Period Closing — module 31)**: the close/reopen/lock routes and
  Periods/Index.vue buttons existed from Phase 1 as a skeleton; this phase enforces the real
  invariants via `AccountingPeriodService` (`app/Domain/Accounting/Services/`) so the demo front
  end can't be bypassed:
  - **Closing is sequential**: `close()`/`lock()` refuse when any EARLIER period in the same FY is
    still open ("Close earlier periods first: … in sequence."). `reopen()` is refused when any
    LATER period is **locked** (locked = permanent, PG-style archive; §"reopen after lock" in the
    old Phase1 test was wrong and was rewritten to assert the refusal). Closed periods get
    `closed_at`/`closed_by` stamped (new nullable columns, `accounting_periods`).
  - **Posting into a closed period is already blocked** by `JournalPostingService`'s
    `$period->isOpen()` check (§1.10/phase4) — every posting engine flows through it, so closing a
    period freezes its ledger with zero additional work; the Phase10a test proves a dated journal
    can't post (flash error 'not open').
  - **Tenant scoping hole closed**: close/reopen/lock/setActive/update/destroy previously ran on ANY
    period id from ANY company — now `AccountingPeriodService::assertCompanyPeriod()` 403s unless the
    period's fiscal year `company_id` matches the active company (an authenticated user could close
    another company's period via guessed URL). `AccountingPeriodRequest` now also validates the
    `fiscal_year_id` belongs to the active company on create.
  - **Integrity guards**: `setActive` and `destroy` refuse closed/locked periods (previously only the
    active-period delete guard existed); closing/`lock` throws `DomainException` → controller maps to
    `back()->with('error', …)` so the SPA shows a flash instead of a 500.
  - **Permissions**: accountant role gained `period.*` (was `period.view` only — accountants now close
    periods; viewer stays `period.view`). RoleSeeder `sync()`s per role so `db:seed --force` re-grants.
  - Tests (Phase10aCoreTest, 7 tests): close+posting-block, sequence guard, close→reopen restore,
    locked-later-blocks-reopen, setActive/delete 422 on closed, cross-company close 403, accountant-
    vs-viewer close. The old Phase1 test was updated to the new permanent-lock semantics.
- **Phase 10b domain added (Year-End Closing — module 32)**: `FiscalYearClosingService` +
  `FiscalYearClosingException` in `app/Domain/Accounting/` replaces the naive FiscalYear
  `close()`/`reopen()` flips from Phase 1.
  - **YEC journal**: with all periods closed + FY not active + FY not already closed, closing
    computes per-account signed nets from POSTED journal lines in the FY range and books a
    balanced `source_type=closing` (prefix **YEC**, `YEC-{year}-%04d`) journal dated the FY's last
    day in its LAST period: each income leaf Dr (its net), each expense leaf Cr (its net), and the
    difference to Retained Earnings (**3211**, seeded postable leaf; missing → error). The journal
    is inserted directly as `posted` (all periods are closed so the normal isOpen gate must be
    bypassed — deliberate, mirroring opening posts), with `source_id = fiscal_year.id`.
  - **Carry-forward opening**: if a later fiscal year exists with a first period, asset/liability/
    equity nets (income/expense are excluded — they were zeroed) are carried into an
    `source_type=opening` (OB) journal dated the next FY's first day. This runs in the SAME
    transaction as the YEC insert, so the carried RE balance comes from the closing lines.
  - **Gotcha hit in tests (§1.23 redux)**: `->whereBetween('journals.journal_date',
    [$fy->start_date, $fy->end_date])` silently EXCLUDES the YEC journal — a `date` cast stores
    `2040-12-31 00:00:00` and SQLite compares the string `'2040-12-31 00:00:00' > '2040-12-31'`, so
    the closing journal (dated the FY's last day) was dropped from the range and the carry-forward
    saw only cash, not the RE credit. Always bound ranges with `->startOfDay()`/`->endOfDay()`.
  - **Guards**: close refused when periods are still open, when the FY is `is_active`, or when
    already closed; reopen refused when ANY later FY is already closed (sequence). Both map
    `FiscalYearClosingException` → `back()->with('error')`.
  - **Permissions**: accountant gained `fiscal_year.close` + `fiscal_year.reopen` (was view-only;
    close/reopen were previously company-admin-only via `*`). `FiscalYearController` now catches
    the exception; `FiscalYears/Index.vue` gained a Reopen button for closed years (Close already
    existed, hidden while active); `Journals/Show.vue` labels `closing` as 'Year-End Closing' with
    a "View Fiscal Year" source link (note: `fiscal-years.index` is the only FY route — there is
    no show route).
  - Tests (Phase10bCoreTest, 6 tests): close books RE + carries forward (asserts YEC/OB numbers,
    journal dates, RE/cash line amounts, balance), open-period refusal, active refusal, later-year-
    closed blocks reopen, reopen works, accountant close vs viewer 403. Test gotcha: `POST
    /journals` only creates a DRAFT (store never posts) — the helper must ALSO hit
    `journals.post` before balances exist.
- **Phase 11 UI hardening (sidebar + pagination, no new modules)**:
  - §1.9-style silent bug: Every page template wraps `AuthenticatedLayout`, so the layout (and its
    overflow-scroll `<nav>`) unmounts/remounts on EVERY Inertia navigation → the sidebar's
    `scrollTop` silently reset to the top. **Module-scope state dev trick**: state that must survive
    remounts cannot live in `<script setup>` (per-instance!) — declare it in a plain module-level
    `<script lang="ts">{ let savedSidebarScroll = 0; }</script>` BLOCK, and restore in the template-ref
    `watch(navRef, ...)` callback (NOT `onMounted`, which can run before the ref watcher binds).
  - Sidebar is now **collapsible topic groups** (`visibleGroups` computed filters per-permission;
    `initGroupOpen` reads `localStorage` `sidebar-open-{label}`, defaults open for Overview or the
    group containing the active route via `isActive`; `toggleGroup` persists). Submenu items use
    `v-show` (not `v-if`) so the DOM keeps links for CDP nav scripts and keep-visible nav.
  - `Pagination.vue` renders Laravel's `&laquo; Previous`/`Next &raquo;` labels as pure **chevron
    icons** (disabled spans when `url` is null), numeric page buttons, and "Page X of Y" where the
    total is derived from the LAST numeric page label (scanning left past the `...` separator) — never
    `links.length - 2` (breaks with ellipses).
  - Headless verification: 44-route sidebar click pass + pagination render + scroll-preservation
    assertions (`nav-sidebar.cjs`/`nav-all.cjs`/`nav-pager.cjs` patterns in the temp dir).
  - **Full-project responsive pass (390/768/1024/1440 verified)**: every `<table>` in
    `Pages/`/`Components/` got a horizontal-scroll min-width — `class="w-full divide-y …"` →
    `class="min-w-[760px] w-full divide-y …"` (dense audit/aging/statement tables use
    `min-w-[1080px]`; old `min-w-full` variants → `min-w-[760px] lg:min-w-full`) and its wrapper
    `overflow-hidden rounded-* … shadow-sm` → `overflow-x-auto …`. MAJOR trap: a naive
    string-transform script silently SKIPS files whose class attr has extra classes between
    `class="` and the table tokens (e.g. `<table v-else class="mt-5 w-full divide-y …">`) —
    grep-verify with `grep -rn '<table' … | grep 'w-full divide' | grep -v min-w` AND re-run the
    transform till it reports 0 pending files, never trust "changed files: N" once.
  - Form/modal grids inside shared `Modal.vue` use `grid-cols-1 … sm:grid-cols-2` (bare
    `grid grid-cols-2 gap-4` in a `<dialog>` crushes the form on a phone); totals rows
    (`grid-cols-3`/`grid-cols-4`) and 3-stat-card rows (`grid-cols-3 gap-3`) collapse to one
    column below `sm`. **WARNING — `col-span-2` inside a `grid-cols-1` grid**: a bare
    `class="col-span-2"` on the first cell (e.g. the Name field) forces the browser to create an
    IMPLICIT second grid track below `sm`, so every following field gets squeezed into a narrow
    ~72px first track — the modal looks "broken" on phones while it is pixel-perfect on desktop.
    Always use the responsive span (`sm:col-span-2`) so the span exists only when the grid is
    multi-column. Verify with a width sweep (`setDeviceMetricsOverride` 320→414→640→768…) that
    asserts `gridTemplateColumns` is single-track (`288px`) on phones and equal 2 tracks
    (`312px 312px`) from 640 up — overlap detection + column count, not just field tops. `Modal.vue` was upgraded to the Jetstream flex-centering pattern —
    `flex min-h-full items-center justify-center` inside a `md:flex` scroll-region wrapper — so
    modals are vertically centered on every device.
  - Topbar: company-switcher text is `hidden sm:inline` (icon-only on xs); `<main>` is
    `px-4 py-6 sm:px-6` (was `ml-4 mr-2 py-6 sm:px-6 lg:max-w-full`). Verified via a 390px/768px
    headless pass (`nav-mobile.cjs`): `document.documentElement.scrollWidth - clientWidth` must be
    ≤ 1 px on EVERY route (tables scroll inside their containers, never the page body) plus the
    approval modal opens centered (`left≥0`, `right≤vw`, `top≥0`, `bottom≤vh`). A `col-span-2`
    first field makes a "fields[0].top > fields[1].bottom" stacked-check a false positive — compare
    a field pair that genuinely shares a row.

- **Aliases in `bootstrap/app.php`**: `'permission' => EnsurePermission::class`; Inertia header
  middleware appended to the `web` group.
- **Vue page patterns**:
  - Themed list pages split into `Pages/<Kebab>/Index.vue`, `Create.vue`, `Edit.vue`
    (or modal-based like Currencies).
  - `PageHeader` (title/description + `#actions`), `SearchInput` (must guard `route().current()`),
    `Pagination :links="..."`, `StatusBadge`.
  - Breadcrumbs are NOT added per page — `Components/Breadcrumbs.vue` renders once in
    `AuthenticatedLayout`'s `<main>` and derives the chain (`Home > Module > Create/Edit X`)
    from the current route base + segment (MODULE_LABELS/SINGULAR_LABELS/CREATE_LABELS maps
    there). New modules only need entries in those maps.
  - Period select-driven lists (e.g. Periods) navigate via `router.get(route(...), { fiscal_year_id })`.
- **Types**: Vue `defineProps` types must exactly match the Inertia prop shape (see §1.1).
  Shared app types exported from `resources/js/types/index.d.ts`.

---

## 3. Verification checklist (mandatory before "done")

1. `npm run build` (runs `vue-tsc` type-check + `vite build`) — must pass clean.
2. `php artisan test` — whole suite green (SQLite in-memory; Docker can stay up or down).
3. Headless-Chrome SPA navigation pass against the running app
   (`http://localhost:8000`, demo login `admin@demobusiness.local` / `password`):
   log in → click every sidebar link → assert correct heading + URL + **zero** console/network errors.
   This catches Vue render crashes that PHP tests miss.
4. If Docker touched, confirm `docker compose up` still boots and serves `/login` 200.

---

## 4. Environment & access

- App root: repo root (`/Users/mdibrahim/sajib/project/accounting/`). `app/` contains only
  application code (Domain/Http/Models/Providers/Services/Support). Architecture blueprint:
  `accounting-erp-architecture.md` (repo root).
- Stack: Laravel 11, Vue 3 + Inertia (v2, Ziggy), TS, Tailwind, MySQL in Docker
  (host port 3307), SQLite `:memory:` for tests.
- Docker: from the repo root run `docker compose up -d --build` → app at http://localhost:8000;
  auto-migrate + auto-seed on boot via `.env.docker` (`DB_MIGRATE=true`, `DB_SEED=true`).
  Assets are pre-built (`npm run build`); do **not** leave `public/hot` around.
- Demo super admin: `admin@demobusiness.local` / `password`.
- Command reference: `COMMANDS.md`.