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



ACCOUNTING PROJECT — MODULE LIST

PHASE 1 — FOUNDATION
01. Company
02. Branch
03. User & Role
33. System Settings
05. Fiscal Year & Period
06. Currency

PHASE 2 — ACCOUNTING SETUP
07. Tax/VAT
08. Chart of Accounts
04. Accounting Configuration

PHASE 3 — MASTER DATA
09. Customer
10. Supplier
11. Product/Service
12. Warehouse

PHASE 4 — CORE ACCOUNTING
20. General Journal
    - Ledger (Derived)
    - Opening Balances

PHASE 5 — SALES & PURCHASE
14. Sales
15. Purchase
16. Accounts Receivable
17. Accounts Payable

PHASE 6 — INVENTORY & CASH
13. Inventory
18. Cash & Bank
19. Expense

PHASE 7 — ADVANCED MODULES
21. Fixed Assets
22. Payroll
23. Budget

PHASE 8 — REPORTING
24. Accounting Reports
25. Financial Statements
30. Dashboard

PHASE 9 — GOVERNANCE & SUPPORTING
27. Approval Workflow
26. Audit Trail
28. Notifications
29. Documents/Attachments

PHASE 10 — PERIOD & YEAR-END CLOSING
31. Period Closing
32. Year End Closing