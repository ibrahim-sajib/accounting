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