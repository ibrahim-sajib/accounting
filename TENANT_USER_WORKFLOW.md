# সম্পূর্ণ ওয়ার্কফ্লো — ডাটাবেস-পার-টেন্যান্ট (শুরু থেকে শেষ)

একটা কোম্পানি = একটা নিজস্ব ডেটাবেস। এই ডকুমেন্টে পুরো ফ্লো একসাথে —
সুপার অ্যাডমিন থেকে শুরু করে ক্রেতা কোম্পানির প্রতিদিনের কাজ পর্যন্ত
— ঠিক যে মডেলটা ধরে নেওয়া হয়েছে।

> সংক্ষিপ্ত (টেকনিক্যাল) রেফারেন্স: `TENANT_OVERVIEW.md`
> বাকি মডিউলের স্ক্রিন-ভিত্তিক বিস্তার: `ACCOUNTING_USER_WORKFLOW.md`
> কমান্ড: `COMMANDS.md`

---

## ধারণাটা (এক লাইনে)

- **Control-plane** (`accounting_erp`): ইউজার, কোম্পানি, রোল/পারমিশন, অ্যাক্সেস রেজিস্ট্রি, নোটিফিকেশন ইনবক্স, platform seed data
- **Per-tenant** (`accounting_tenant_{company_id}`): ওই কোম্পানির সব বিজনেস ডেটা + নিজের master data + নিজের audit log — সম্পূর্ণ আলাদা

নতুন কোম্পানি তৈরি মানেই **নতুন আলাদা ডেটাবেস** তৈরি → migrate → seed। তারপর ওই কোম্পানির লোকজনের সব কাজ ওই ডেটাবেসেই।

---

## ধাপ ১ — সুপার অ্যাডমিন লগইন

`admin@demobusiness.local` / `password` → লগইন control-plane-এ (`mysql`)।
সুপার অ্যাডমিনের কাছে platform স্ক্রিন খোলা থাকে: **Companies**, **Users**, **Roles**, Profile, Notifications।
কোম্পানির ভেতরে ঢুকে ব্যবসার পেজও দেখতে পারে (সেই কোম্পানির tenant DB-তে)।

## ধাপ ২ — কোম্পানি তৈরি (কী হয় এক ক্লিকে)

Companies → **Create Company** → নাম/বasis/status দিলে `POST /companies`-এ এই ক্রমে চলে:

1. **Company row** control-plane-এ তৈরি
2. **Master data seed** (platform-এ): Currency, Fiscal Year + Period, COA, Tax, Accounting Setting, Expense/Asset Category, Department, System Setting
3. **Company Admin অটো-তৈরি** — `admin@<কোম্পানির-নাম>.local` / `password` (email verify করা, company-admin রোল + default access) — flash-এ credential দেখায়
4. **Dedicated ডেটাবেস তৈরি** — `accounting_tenant_{id}`:
   - `CREATE DATABASE` (root connection)
   - `GRANT` (অ্যাপ অ্যাকাউন্ট পায় ওই DB-তে 권한)
   - **migrate** — সব 72টা table, FK সহ
   - **registry copy** (FK-safe ক্রম) — companies → users (admin + সব সুপার অ্যাডমিন) → branches → permissions (152) → roles (16) → role_permissions → user_roles → user_company_access
   - **master seeders আবার চলে** — COA (100 অ্যাকাউন্ট), Tax, Accounting Setting, Currency, FiscalYear, Product Category/Unit, Expense Category, Asset Category, Department, Role — এগুলো idempotent
5. Audit + redirect (flash-এ admin credential)

ফলাফল: নতুন কোম্পানির জন্য **পূর্ণাঙ্গ নিজস্ব DB**, সাথে তার admin login।

CLI দিয়েও একই কাজ: `php artisan tenant:provision {id}` / `tenant:migrate {id}`।

## ধাপ ৩ — ক্রেতা (Company Admin) লগইন

ইমেইলে পাওয়া credential দিয়ে লগইন → লগইন control-plane-এর ইউজার রেজিস্ট্রিতেই যাচাই হয়।
লগইনের পর `SetActiveCompanyContext` ওই ইউজারের default কোম্পানি সেট করে।

**এখান থেকেই `SelectTenantDatabase` middleware কাজ শুরু করে:** প্রতিটা business route-এর
''default connection'' ঘুরে যায় ওই কোম্পানির `tenant_{id}` DB-তে। ফলে:

- Dashboard, Journals, Sales, Receivables, Purchase, Payables, Inventory, Payroll, Fixed Assets, Budget, Expense, Cash & Bank, Reports, Statements, Settings, **Audit** — সব **নিজের DB**-তে read/write
- কাস্টমার/সাপ্লায়ার/প্রোডাক্ট/জার্নাল/রসিদ যে ফাঁকা DB-তে ঢোকে, সে-ই কোম্পানির সব ডেটা
- **Audit log** ওই tenant DB-তেই জমে (per-tenant)

Platform স্ক্রিন (Companies/Users/Roles/Profile/Notifications) উল্টো **control-plane-এই** থাকে —
সুপার অ্যাডমিনের গভর্নেন্স যেন নষ্ট না হয়।

## ধাপ ৪ — প্রতিদিনের কাজ (ক্রেতা হিসেবে, সব নিজের DB-তে)

মডিউলগুলো ধারাবাহিকভাবে ব্যবহার হয়, প্রতিটা কাজের সাথে সাথে জার্নাল তৈরি হয় (posting) —
সব জার্নাল/লাইন `tenant_{id}`-এ:

Sales: Invoice (draft → post `SINV`) → Receipt/Records (RCT) → Receivables (অনাদায়ি/aging/write-off)
Purchase: Bill (draft → post `PUR`) → Payment (PMT) → Payables
Master data: Customer/Supplier/Product/Warehouse
Inventory: Stock ledger, Adjustment (ADJ), Transfer (TR)
Payroll: Run (PYR) → Salary Payment (SP)
Fixed Assets: Capitalize (FA) → Depreciation (DEP) → Disposal (DSP)
Budget → variance; Reports/Statements; Expense (EXP); Cash & Bank (CBT)
Approval 필요 হলে: send for approval → Notifications (control-plane inbox) → approve/reject
Year-End: Period close → Fiscal Year close → YEC + OB carry-forward

## ধাপ ৫ — ইউজার/রোল ম্যানেজমেন্ট (কীভাবে tenant-এ প্রতিফলিত হয়)

Company Admin নিজের লোক (accountant etc.) বানায় **Users** থেকে (platform screen) →
`TenantManager::syncUser()` এখন সাথে সাথে ওই ইউজারকে (row + roles + role_permissions + UCA)
তাদের reachable সবক'টা tenant DB-তে mirror করে, যাতে:
- ওই ইউজার লগইন করে নিজের কোম্পানির business পেজ চালাতে পারে
- Audit-এর `created_by` FK ঠিক থাকে
- Permission check (control-plane) আর tenant copy মিলে যায়

## কোথায় কী থাকে (তাড়াতাড়ি দেখার জন্য)

| কাজ / ডেটা | ডেটাবেস |
| --- | --- |
| Login, users, companies list, roles, permissions, UCA | control-plane `mysql` |
| Company created-by info (registry) | control-plane + tenant mirror |
| Notifications inbox | control-plane (কারণ ফিল্টার `data->company_id` দিয়ে) |
| সব business data + journals + master data + stock + payroll + fixed asset + budget | `accounting_tenant_{id}` |
| Audit log | `accounting_tenant_{id}` (per-tenant) |
| Platform-এ user বানালে tenant mirror | `syncUser()` নিজে থেকেই |

## যাচাই (কীভাবে দেখবে যে সব সত্যিই নিজের DB-তে)

```bash
docker compose exec mysql mysql -uroot -proot
SHOW DATABASES LIKE 'accounting_tenant_%';      # প্রতি কোম্পানি = ১টা
USE accounting_tenant_1; SHOW TABLES;           # 72 টেবিল
SELECT COUNT(*) FROM customers;                 # ওই কোম্পানির ডেটা
USE accounting_erp; SELECT COUNT(*) FROM customers;  # এখানে ০ (অথবা পুরনো)
```

কাস্টমার বানিয়ে দুই DB-তে গুনলে দেখা যায়: নতুন ডেটা **শুধু** tenant DB-তে যায়।

## সীমাবদ্ধতা (এই মডেলের বাইরে, ভবিষ্যতে)

- Cross-company data copy (এক tenant থেকে আরেকটায়) এখনো নেই
- Tenant-level user delete sync এখনো পুরোপুরি নেই (soft-delete হলে tenant copy থাকে)
- Buyer-এর নিজস্ব per-tenant User management (split UI) ভবিষ্যতের কাজ — এখন user management platform-এই

## কোথায় কী লেখা আছে

- এই ফাইল: সম্পূর্ণ ফ্লো (শুরু→শেষ)
- `TENANT_OVERVIEW.md`: tenant DB-র টেকনিক্যাল বিবরণ (provision, runtime switching, Stage 2)
- `ACCOUNTING_USER_WORKFLOW.md`: প্রতিটা বিজনেস মডিউলের স্ক্রিন-ভিত্তিক বিস্তারিত ওয়ার্কফ্লো
- `COMMANDS.md`: সব artisan/docker কমান্ড