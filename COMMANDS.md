# Accounting ERP — Command Reference

All commands below run from the project root: `<repo>/app`.

---

## Quick start

### Docker (recommended — full stack: MySQL + PHP-FPM + nginx)

```bash
docker compose up -d --build      # build once, boot mysql + app + web
```

- Web app: **http://localhost:8000**
- MySQL on host port **3307** (container port 3306)
- On first boot the app container auto-runs `php artisan migrate` and `php artisan db:seed` (idempotent).
- Vite runs on your machine (not in Docker):
  ```bash
  npm install
  npm run dev
  ```

**Demo login:** `admin@demobusiness.local` / `password` (Super Admin)

### Native (Laravel + local MySQL on 127.0.0.1:3307)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build          # or `npm run dev`
php artisan serve      # http://localhost:8000
```

---

## Docker commands

| Command | Purpose |
| --- | --- |
| `docker compose up -d` | Start the stack in the background |
| `docker compose up -d --build` | Rebuild images, then start |
| `docker compose ps` | Show service status (mysql/app/web) |
| `docker compose logs -f app` | Follow the PHP-FPM / app logs |
| `docker compose logs -f web` | Follow the nginx logs |
| `docker compose logs -f mysql` | Follow the MySQL logs |
| `docker compose down` | Stop containers (keeps DB volume) |
| `docker compose down -v` | Stop containers AND delete the MySQL volume (data loss) |
| `docker compose exec app php artisan ...` | Run any artisan command inside the app container |

Examples:

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan route:list
docker compose exec mysql mysql -uaccounting -psecret accounting_erp
```

Environment for the app container lives in `.env.docker` (also overridable via the `environment:` block in `docker-compose.yml`). `DB_MIGRATE=true` and `DB_SEED=true` control the auto-migrate/auto-seed on boot.

---

## Migrations

| Command | Purpose |
| --- | --- |
| `php artisan migrate` | Run all pending migrations |
| `php artisan migrate:fresh` | Drop all tables, run all migrations |
| `php artisan migrate:fresh --seed` | Fresh migrate, then run seeders |
| `php artisan migrate:rollback` | Roll back the last migration batch |
| `php artisan migrate:rollback --step=3` | Roll back the last 3 batches |
| `php artisan migrate:status` | Show which migrations have run |
| `php artisan migrate:refresh` | Roll back everything, re-migrate |
| `php artisan migrate --force` | Run without confirmation (needed in production / Docker) |

Make a new migration (project default is a single `database/migrations` folder):

```bash
php artisan make:migration create_gl_accounts_table
php artisan make:migration add_vat_rate_to_tax_settings_table --table=tax_settings
```

---

## Seeders

| Command | Purpose |
| --- | --- |
| `php artisan db:seed` | Run the `DatabaseSeeder` |
| `php artisan db:seed --class=PermissionSeeder` | Run a specific seeder |
| `php artisan db:seed --force` / `-f` | Run without confirmation (Docker boot, production) |
| `php artisan db:seed --class=PermissionSeeder --force` | Reseed permissions idempotently |

All seeders are idempotent (`firstOrCreate`) and safe to re-run.

`DatabaseSeeder` calls, in order:

1. `PermissionSeeder` — all module permissions (including `company.switch`)
2. `RoleSeeder` — `super-admin` (`*`), `company-admin` (`*`), plus functional roles
3. `CompanySeeder` — **Demo Business Ltd**, HQ branch, and the super admin user
4. `CurrencySeeder` — BDT, USD, EUR, GBP, INR, PKR
5. `FiscalYearSeeder` — a fiscal year with 12 open monthly periods
6. `SystemSettingSeeder` — default company settings

Make a new seeder:

```bash
php artisan make:seeder GlAccountSeeder
```

When a new company is created from the UI, `CompanyController::store()` runs the currency, fiscal-year, and system-setting seeders for it automatically.

---

## Frontend / Vite

| Command | Purpose |
| --- | --- |
| `npm install` | Install JS dependencies |
| `npm run dev` | Start the Vite dev server (HMR on host, nginx picks up the `hot` file automatically) |
| `npm run build` | Type-check with `vue-tsc`, then production build to `public/build` |
| `npm run build -- --watch` | Rebuild on every change |

---

## Tests

| Command | Purpose |
| --- | --- |
| `php artisan test` | Run the whole suite |
| `php artisan test --filter=Phase1CoreTest` | Run one test file |
| `php artisan test --filter=test_super_admin_can_view_companies_listing` | Run a single test |
| `php artisan test --testsuite=Feature` | Run only feature tests |

Tests use **SQLite in-memory** (`phpunit.xml`), so no MySQL is required and the Docker stack can be left running or stopped.

---

## Useful artisan commands

| Command | Purpose |
| --- | --- |
| `php artisan serve` | Serve the app on http://localhost:8000 |
| `php artisan route:list` | List all routes, names, URI, middleware |
| `php artisan route:list --name=companies` | Filter routes by name |
| `php artisan tinker` | Interactive PHP shell (dev exploration) |
| `php artisan about` | App/config summary (env, drivers, versions) |
| `php artisan storage:link` | Symlink `storage/app/public` → `public/storage` (needed for uploaded logos) |
| `php artisan optimize` | Cache config/routes/views (also `optimize:clear` to flush) |
| `php artisan config:clear` / `config:cache` | Flush / cache config |
| `php artisan route:clear` / `route:cache` | Flush / cache routes |
| `php artisan key:generate` | Generate `APP_KEY` (after first `cp .env.example .env`) |

---

## Domain / module layout

New modules follow the domain-first structure (see `../accounting-erp-architecture.md`):

```
app/Domain/<Module>/
├── Http/
│   ├── Controllers/<ModuleController>.php
│   └── Requests/<ModuleRequest>.php
├── Models/<Module>.php
├── Services/<ModuleService>.php
└── ... 
```

Routes for each module are registered in `routes/web.php` behind the `auth` + `verified` middleware, guarded by the `permission:<module>.<action>` middleware.