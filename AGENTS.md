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