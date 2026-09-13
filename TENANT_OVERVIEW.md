# Database-per-Tenant — Flow & Operations

How the sellable accounting product isolates each company in its own MySQL
database, and the exact flow from "super admin creates a company" to "the
buyer manages everything inside that tenant".

Status: **Stage 1 complete (provisioning, verified live)** · Stage 2 (runtime
connection switching) planned.

---

## 1. Mental model: control-plane vs. tenant databases

| Layer | Database | Purpose |
| --- | --- | --- |
| Control-plane | `accounting_erp` (`mysql` connection) | Users, companies, RBAC, `user_company_access`, audit, platform seed data |
| Tenant | `accounting_tenant_{company_id}` (`tenant_{id}` connection) | A full schema mirror of the app (all 72 tables, FK constraints kept) holding **one** company's rows and its master data |

A company's tenant database does **not** hold its own `companies` row plus a
bunch of other companies' rows — it holds only that one company's data, so the
same Eloquent queries return exactly that tenant's rows when the app runs
against the tenant connection (Stage 2).

### Env vars & config

- `TENANCY_ENABLED=true` in `.env` / `.env.example` / `.env.docker`
  (defaults to on; `TenantManager::enabled()` additionally requires the default
  DB driver to be `mysql`, so SQLite test runs bypass tenancy completely).
- `DB_ROOT_USERNAME=root`, `DB_ROOT_PASSWORD=root` — a privileged account on
  the MySQL server used to `CREATE DATABASE` + `GRANT` each tenant DB
  (`config/database.php` → `database.connections.tenant_admin`).
- `config/tenancy.php` — `enabled`, `database_prefix` (default
  `accounting_tenant_`).
- Running app user (`accounting`) is granted privileges **only** on the tenant
  DB it should access, so one tenant can never read another's database even
  with a guessed connection name.

---

## 2. The exact flow: super admin creates a company

Triggered by `POST /companies` (Companies page → Create). `CompanyController::store()`
runs, in order:

1. **Create** the `Company` row on the control-plane.
2. **Provision defaults** — company-scoped seeders already run for the platform
   (currencies, fiscal year + periods, COA + tax + accounting settings, master
   data, expense/asset categories, departments, system settings).
3. **Create the Company Admin** — `admin@<kebab-name>.local` / `password`,
   granted the `company-admin` role (company-scoped) + a default
   `user_company_access` row. The flash message on the Companies page shows
   these credentials.
4. **Provision the tenant database** (`TenantManager::provision($company)`):
   - `CREATE DATABASE accounting_tenant_{id}` via the root `tenant_admin`
     connection.
   - `GRANT ALL ON accounting_tenant_{id}.* TO 'accounting'` — without this the
     app account can't touch the new DB.
   - `migrate()` — every migration against the tenant's own `tenant_{id}`
     connection (full schema, foreign keys preserved).
   - `copyPlatformBoilerplate()` — copies this company's rows from the
     control-plane into the tenant in an FK-safe order:
       `companies` (audit stamps nulled, §1.29 circular-FK fix) →
       `users` (tenant admin + all super admins; super admins' `company_id`
       remapped to NULL because their home company doesn't exist in the tenant)
       → restore company stamps → `branches` → `permissions` (all 152) →
       `roles` (16) → `role_permissions` (972) → `user_roles` →
       `user_company_access`.
   - `runMasterDataSeeders()` — re-runs the company-scoped seeders
     (ChartOfAccounts, Tax, AccountingSetting, Currency, FiscalYear, MasterData,
     ExpenseCategory, AssetCategory, Payroll, SystemSetting). All idempotent;
     re-running provision on an already-seeded tenant is a no-op.
5. **Audit-log** the provisioning, then redirect back with the admin creds flash.

Verified outcome of a fresh tenant DB: 72 tables, 1 company, 2 users (the
company admin + the global super admin), 152 permissions, 16 roles, 100
accounts, 2 tax types, 2 fiscal years, 6 currencies, 5 expense categories,
4 asset categories, 5 departments, 1 accounting_settings.

---

## 3. CLI toolkit (same machinery, manual trigger)

| Command | Purpose |
| --- | --- |
| `php artisan tenant:provision {companyId}` | Create the tenant DB (if missing), migrate, copy boilerplate, run master seeders |
| `php artisan tenant:migrate {companyId}` | Create (if missing) + migrate the tenant schema only |

In Docker: `docker compose exec app php artisan tenant:provision 1`.

Provisioning is idempotent — safe to re-run. If the tenant DB already exists it
is re-migrated (`migrate` skips applied migrations) and all seeders `firstOrCreate`.

Direct MySQL inspection (demo environment):

```bash
docker compose exec mysql mysql -uroot -proot
SHOW DATABASES LIKE 'accounting_tenant_%';
USE accounting_tenant_1; SHOW TABLES;
```

---

## 4. Why the copy order matters (the two bugs that got fixed)

Both are documented in `AGENTS.md` §1.29:

1. **Circular foreign keys** — `companies.created_by → users` and
   `users.company_id → companies`. For UI-created companies `created_by` is the
   acting super admin, so copying `companies` first into an empty tenant violates
   the FK. Seeded companies happen to have `created_by = NULL`, which is why the
   CLI demo path appeared to work while the web path 500'd at the RoleSeeder.
2. **MySQL INSERT IGNORE swallows FK failures (no 1452).** `insertOrIgnore()`
   silently skips a row whose FK parent is missing — no exception, zero rows.
   A failed `companies` copy therefore dominoed into users/roles/pivots all
   ending up empty while `permissions` (no FKs) landed, and every
   `tenant.copy` log read looked perfect.

---

## 5. Stage 2 (planned): runtime connection switching

Today the single `accounting_erp` database is still the runtime source for
transactional screens; tenant databases are the write-ahead provisioning target.
Stage 2 makes them the real runtime source:

- A middleware after auth sets the default connection to
  `tenant_{active_company_id}` (registered on demand by `TenantManager`) so
  every tenant-scoped query runs in the company's own database.
- Super-admin platform screens (Companies, Users, Roles, user access) pin the
  `mysql` control-plane connection.
- `Clone` operations across companies ship data between `tenant_A` and
  `tenant_B` directly.
- Test infra gains a multi-connection mode (platform + one tenant SQLite file).

Known open decisions for Stage 2: where audit logs live (per-tenant vs.
control-plane), and how a super admin browsing a company sees that company's
tenant data vs. platform data.