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