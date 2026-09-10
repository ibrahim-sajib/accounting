# Accounting ERP

A modular, multi-company accounting ERP built with **Laravel 11**, **Vue 3**, **Inertia.js**, **TypeScript**, **Tailwind CSS** and **MySQL**.

## Stack

- Backend: Laravel 11 (domain-first modules in `app/Domain/*`)
- Frontend: Vue 3 + Inertia.js + TypeScript + Tailwind (SPA-style navigation)
- Database: MySQL (`accounting_erp`), SQLite in-memory for tests
- Dev tooling: Docker Compose (MySQL, PHP-FPM, nginx), Vite, Ziggy

## Getting started

```bash
docker compose up -d --build      # app on http://localhost:8000
npm install
npm run dev                       # Vite dev server on the host
```

Open **http://localhost:8000** and log in:

| Email | Password | Role |
| --- | --- | --- |
| `admin@demobusiness.local` | `password` | Super Admin |

## Modules (current)

Phase 1 — setup modules shipped:

- Companies, Branches, Users, Roles & Permissions
- Fiscal Years, Accounting Periods
- Currencies (+ exchange rates)
- System Settings
- Multi-company active-context switching

## Commands

All migration, seeder, Docker, frontend and test commands are documented in **[COMMANDS.md](COMMANDS.md)**.

## Architecture

See **[../accounting-erp-architecture.md](../accounting-erp-architecture.md)** for the module map, data model and accounting rules.