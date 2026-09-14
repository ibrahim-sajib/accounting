# হিসাব ব্যবস্থাপনা সফটওয়্যার — সম্পূর্ণ ইউজার ওয়ার্কফ্লো ডকুমেন্টেশন

**ফাইলের নাম:** `ACCOUNTING_USER_WORKFLOW.md`

> **টেন্যান্ট-মডেল নোট:** এই ডকুমেন্টের ফ্লো এখন **ডাটাবেস-পার-টেন্যান্ট** মডেলে চলে — প্রতিটা কোম্পানির সব ডেটা তার নিজের **`accounting_tenant_<কোম্পানির নাম>`** ডেটাবেসে থাকে (যেমন "Demo Business Ltd" → `accounting_tenant_demo_business_ltd`)। রোল মডেল: **System Super Admin = প্রোডাক্ট মালিক (আপনি)**, যিনি প্রতিটি টেন্যান্টে সেই কোম্পানির সুপার অ্যাডমিন হিসেবে কপি হন; কোম্পানি তৈরি হলে অটো-তৈরি হয় **Company Admin** (সব মেনুতে অ্যাক্সেস, ইউজার/রোল বানাতে পারে)। শুরু থেকে শেষ পর্যন্ত সেই মডেলের ধাপ-দ্বারা-ধাপ বর্ণনা আছে `TENANT_USER_WORKFLOW.md`-এ। এই ফাইলের মডিউলগুলো (Phase 1–13) সেই টেন্যান্ট ডেটাবেসের **ভেতরেই** কাজ করে; প্রোডাক্ট মালিকের platform স্ক্রিন (Companies/Users/Roles) control-plane ডেটাবেসে চলে।

> এই ডকুমেন্টটি একটি **প্রোডাক্ট ওয়ার্কফ্লো ব্লুপ্রিন্ট**। এখানে কোনো Laravel কোড, Vue কোড, ডেটাবেজ স্কিমা, API বা ক্লাস/কন্ট্রোলার নেই। এখানে শুধু বর্ণনা করা হয়েছে — ব্যবহারকারী কী দেখেন, কোথায় যান, কোন মেনু খোলেন, কী তথ্য দেন, সাবমিট করার পর কী হয়, স্ট্যাটাস কী হয়, পরবর্তী ধাপ কী, এবং পর্দার আড়ালে হিসাবের উপর কী প্রভাব পড়ে। এই ডকুমেন্ট ডেভেলপার, প্রোডাক্ট ডিজাইনার, QA ইঞ্জিনিয়ার এবং সাধারণ হিসাব-ব্যবহারকারী — সবার জন্য বোধগম্য হওয়ার উদ্দেশ্যে তৈরি।

---

## সূচিপত্র

0. **শুরু — ডেটাবেস সেটআপ, সিডিং ও টেস্ট ফ্লো (Fresh Database Guide)**
1. Phase 1 — কোম্পানি ও সিস্টেম সেটআপ
2. Phase 2 — অ্যাকাউন্টিং ফাউন্ডেশন
3. Phase 3 — মাস্টার ডেটা
4. Phase 4 — ওপেনিং ব্যালেন্স
5. Phase 5 — সেলস ওয়ার্কফ্লো
6. Phase 6 — পারচেজ ওয়ার্কফ্লো
7. Phase 7 — ইনভেন্টরি ওয়ার্কফ্লো
8. Phase 8 — অ্যাকাউন্টস রিসিভেবল
9. Phase 9 — অ্যাকাউন্টস পেয়েবল
10. Phase 10 — ক্যাশ ও ব্যাংক
11. Phase 11 — এক্সপেন্স ম্যানেজমেন্ট
12. Phase 12 — জেনারেল জার্নাল ও অ্যাকাউন্টিং ইঞ্জিন
13. Phase 13 — ফিক্সড অ্যাসেট
14. Phase 14 — পে-রোল
15. Phase 15 — বাজেট
16. Phase 16 — অ্যাপ্রুভাল ওয়ার্কফ্লো
17. Phase 17 — রিপোর্টিং
18. Phase 18 — ফাইন্যান্সিয়াল স্টেটমেন্ট
19. Phase 19 — ড্যাশবোর্ড
20. Phase 20 — নোটিফিকেশন
21. Phase 21 — ডকুমেন্ট/অ্যাটাচমেন্ট
22. Phase 22 — অডিট ট্রেইল
23. Phase 23 — পিরিয়ড ক্লোজিং
24. Phase 24 — ইয়ার এন্ড ক্লোজিং
25. এন্ড-টু-এন্ড বিজনেস জার্নি
26. গ্লোবাল নেভিগেশন স্ট্রাকচার
27. স্ক্রিন-লেভেল ওয়ার্কফ্লো (নমুনা)
28. স্ট্যাটাস ও লাইফসাইকেল
29. অ্যাকাউন্টিং ইমপ্যাক্ট সারাংশ
30. এরর ও এক্সেপশন ফ্লো
31. ইউজার এক্সপেরিয়েন্স নীতিমালা
32. সম্পূর্ণ সিস্টেম ফ্লো
33. মডিউল ডিপেন্ডেন্সি ম্যাপ
34. ইউজার রোল জার্নি
35. দৈনিক / মাসিক / বার্ষিক ওয়ার্কফ্লো
36. হাতে-কলমে ডেটা এন্ট্রি টেস্ট গাইড (Manual Data-Entry Workbook)

---

# শুরু — ডেটাবেস সেটআপ, সিডিং ও টেস্ট ফ্লো (Fresh Database Guide)

> নতুন/খালি ডেটাবেসে কীভাবে সিস্টেমটিকে টেস্ট-রেডি করবেন — কোন মাস্টার ডেটা সিড করা থাকে,
> কোনটি হাতে বানাতে হয়, এবং পুরো সফটওয়্যারটি শুর থেকে শেষের ফ্লো কীভাবে টেস্ট করবেন।

## ১. ফ্রেশ ডেটাবেস তৈরি

ডেটাবেসে কোনো ডেটা নেই এমন অবস্থা (ফ্রেশ) পেতে **সবকিছু মুছে আবার নতুন করে** মাইগ্রেট করুন:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

- `migrate:fresh` → সব টেবিল মুছে, নতুন করে সব মাইগ্রেশন চালায়।
- `--seed` → সাথে সাথে `DatabaseSeeder` চলে; **সব মাস্টার ডেটা স্বয়ংক্রিয়ভাবে ঢুকে যায়**।
- **নেটিভ (Docker ছাড়া):** `php artisan migrate:fresh --seed`

## ২. লগইন ও সুপার অ্যাডমিন — রোল মডেল (product owner + company admin)

এই সফটওয়্যারটি **একটি প্রোডাক্ট**, তাই ইউজার-রোল মডেল সাজানো হয়েছে এভাবে:

| রোল / ইউজার | কে | কীভাবে তৈরি হয় | অ্যাক্সেস |
| --- | --- | --- | --- |
| **System Super Admin** (প্রোডাক্ট মালিক) | আপনি | `CompanySeeder`-এ সিড (ডিফল্ট লগইন) | পুরো প্ল্যাটফর্ম — সব কোম্পানি দেখেন, কোম্পানি তৈরি/মুছে ফেলেন। প্রতিটি তৈরি কোম্পানির টেন্যান্টে এই অ্যাডমিন **সেই কোম্পানির সুপার অ্যাডমিন হয়ে কপি** হয় (সব মেনু, সব টেবিল) |
| **Company Super Admin** (টেন্যান্টে) | = System Super Admin | কোম্পানি তৈরি হলে টেন্যান্টে অটো-কপি | ওই কোম্পানির ভেতরে সুপার অ্যাডমিনের সব ক্ষমতা |
| **Company Admin** | কোম্পানির মুখ্য ব্যবহারকারী | কোম্পানি তৈরি হলে অটো (`admin@<company>.local` / `password`) | `company-admin` রোল = **সব পারমিশন** (`*`) — সব মেনু ব্যবহার করতে পারবেন, ইউজার বানিয়ে রোল দিতে পারবেন, রোল/শাখা বানাতে পারবেন; শুধু platform-স্তরের Companies তালিকা দেখবেন না |
| **অন্যান্য ইউজার** | কোম্পানির কর্মী (Accountant, Sales Executive, Viewer…) | Company Admin / System Super Admin UI-তে তৈরি করেন | ঠিক রোলের পারমিশন অনুযায়ী — তার বেশি নয় |

**ডিফল্ট System Super Admin (সিড থেকে):**
- **ইমেইল:** `admin@demobusiness.local`
- **পাসওয়ার্ড:** `password`
- সিড যা-ও তৈরি করে: **Demo Business Ltd**, শাখা **Head Office (HQ)**, মুদ্রা **BDT**,
  ফিসক্যাল ইয়ার + ১২টি মাসিক পিরিয়ড, **Super Admin** রোল (সব `module.action`-এ `*`)।

> নতুন কোম্পানি UI থেকে বানালে (`Organization → Companies`) `CompanyController`
> নিজস্ব **টেন্যান্ট ডেটাবেস** + কারেন্সি/FY/COA/ট্যাক্স/সেটিংস/ক্যাটাগরি + **Company Admin লগইন**
> সব অটো-তৈরি/সিড করে দেয়।

## ৩. কী কী সিড করা থাকে (Master Data = আলাদা করে ঢোকাতে হবে না)

| মডিউল (সাইডবার) | সিড করা ডেটা | সিডার |
| --- | --- | --- |
| Accounting → Chart of Accounts | সম্পূর্ণ COA — অ্যাসেট/দায়/ইকুইটি/আয়/ব্যয়, পোস্টেবল লিফ সহ | `ChartOfAccountsSeeder` |
| Accounting → Tax & VAT | VAT স্ট্যান্ডার্ড 15%, রিডিউসড 7.5%, জিরো + WHT 3% | `TaxSeeder` |
| Accounting → Accounting Settings | ডিফল্ট AR / AP / ক্যাশ / ব্যাংক / ইনভেন্টরি / সেলস / পারচেস / ভ্যাট অ্যাকাউন্ট | `AccountingSettingSeeder` |
| Accounting → Fiscal Years | একটি FY + ১২টি খোলা পিরিয়ড | `FiscalYearSeeder` |
| Accounting → Currencies | BDT (বেস), USD, EUR, GBP, INR, PKR | `CurrencySeeder` |
| Master Data → Products (ক্যাটাগরি) | Goods, Services, Raw Materials | `MasterDataSeeder` |
| Master Data → Products (একক) | pc, kg, L, bx, hr | `MasterDataSeeder` |
| Expenses → Categories | Rent, Utilities, Salaries, Office Supplies, Travel (+ COA লিঙ্ক) | `ExpenseCategorySeeder` |
| Fixed Assets → Categories | Buildings, Machinery & Equipment, Furniture Fixtures & Computers, Vehicles (+ COA লিঙ্ক) | `AssetCategorySeeder` |
| Payroll → Employees (ডিপার্টমেন্ট) | HR, Finance & Accounts, Sales & Marketing, Operations, IT | `PayrollSeeder` |
| Payroll → Employees (পদবি) | MD, Department Head, Manager, Senior Officer, Officer | `PayrollSeeder` |

## ৪. কী কী সিড করা থাকে না (হাতে বানাতে হবে)

এই ডেটাগুলো **সিড হয় না** — UI থেকে তৈরি করতে হবে:

- **Customer** (Master Data → Customers)
- **Supplier** (Master Data → Suppliers)
- **Product/Service** (Master Data → Products) — ক্যাটাগরি/একক আগেই সিড করা আছে
- **Warehouse** (Master Data → Warehouses) — অন্তত **একটি সক্রিয় গুদাম** লাগবে (ইনভেন্টরি মুভমেন্ট প্রথম যেই সক্রিয় গুদামে যায়)
- **User** সুপার অ্যাডমিন ছাড়া (Organization → Users)
- যেকোনো **Transaction ডেটা** — ইনভয়েস, বিল, রসিদ, পেমেন্ট, এক্সপেন্স, জার্নাল ইত্যাদি
- **Currency Rate** (আগে থেকেই বেস কারেন্সি BDT আছে)
- **Opening Balance** (Phase 4-এ টেস্ট করা হয়)

## ৫. সম্পূর্ণ টেস্ট ফ্লো (শুরু থেকে শেষ — ধাপ ঠিক মান্যতা)

ফ্রেশ ডেটাবেস থেকে পুরো সফটওয়্যার ঠিকঠাক চলে কিনা — **রোল মডেল + অ্যাকাউন্টিং ফ্লো দুই-ই**, এই ক্রমে টেস্ট করুন:

**ধাপ A — প্রোডাক্ট মালিক (System Super Admin)**

1. `php artisan migrate:fresh --seed` চালান → `http://localhost:8000/login` দিয়ে
   `admin@demobusiness.local` / `password` লিখে ঢুকুন। **Organization → Companies**-এ সব কোম্পানি,
   Users-এ সব ইউজার দেখা যায় (এটাই platform)। Demo Business-এ ঢুকে ড্যাশবোর্ড + BDT — সব KPI `0.00`।
2. **Verify মাষ্টার ডেটা** (শুধু দেখুন): COA → ট্যাক্স ও রেট → Accounting Settings → Currencies →
   Fiscal Years/Periods → Expense Categories → Asset Categories → Payroll ডিপার্টমেন্ট/পদবি।

**ধাপ B — কোম্পানি তৈরি → Company Admin**

3. `Organization → Companies → + New Company` দিয়ে একটি নতুন কোম্পানি তৈরি করুন (উদাহরণ: **Boot Test Ltd**)।
   সংরক্ষণের সঙ্গে সঙ্গেই: আলাদা **টেন্যান্ট ডেটাবেস** তৈরি (মাইগ্রেট + মাস্টার-ডেটা সিড), টেন্যান্টে
   **সুপার অ্যাডমিন (আপনি) কপি**, এবং **Company Admin** লগইন অটো-তৈরি —
   `admin@boot.test.ltd.local` / `password` (কোম্পানি তালিকায় ফ্ল্যাশ মেসেজে দেখায়)।
4. **Company Admin** দিয়ে লগইন করুন (dropdown থেকে Boot Test Ltd) → **সব মেনু/সাব-মেনু খোলা** দেখুন
   (Dashboard, Master Data, Sales, Purchase, Inventory, Receivables, Payables, Cash & Bank, Expenses,
   Fixed Assets, Payroll, Budget, Reporting, Transactions, Governance)। টেন্যান্টে কেবল সিড করা মাস্টার
   ডেটা আছে — লেনদেন খালি। প্রমাণ: এটাই আলাদা ডেটাবেস।

**ধাপ C — ইউজার + রোল + শাখা (Company Admin)**

5. Company Admin দিয়ে `Organization → Branches → + Create Branch`-এ দ্বিতীয় শাখা তৈরি করুন (যেমন
   **Boot Outlet**, কোড BO)। **Companies**-এর মতো platform-স্তরের মেনু এখানে নেই (এটি super admin-only)।
6. `Organization → Users → + New User`-এ দুটি ইউজার বানান: `acct@boot.test.ltd.local` (**Accountant**
   রোল) এবং `view1@boot.test.ltd.local` (**Viewer** রোল)। Edit পেজে প্রত্যেকের কাছে **কোন শাখায়** অ্যাক্সেস
   থাকবে বেছে দিন (উদাহরণ: acct → Head Office + Boot Outlet দুটোই, viewer → শুধু Head Office)।
7. প্রতিটি নতুন ইউজার দিয়ে আলাদা লগইন করে যাচাই করুন: মেনু **রোল অনুযায়ী** —
   Accountant যেসব মেনু/অ্যাকশন পায় শুধু সেসবই (পোস্ট/এডিট পারবে), Viewer কেবল view পারবে
   (কোনো Edit/Post বাটন নেই), Company Admin-এর মতো পুরো মেনু **নয়**। acct/viewer **Companies** আর
   (সুপার অ্যাডমিনের) প্ল্যাটফর্ম Users দেখতে পাবে না / 403 পাবে।
8. **এক ইউজার = এক কোম্পানি:** একই ইমেইল প্ল্যাটফর্ম জুড়ে **unique** — দ্বিতীয়বার ঢোকালে validation
   error। ইউজার কেবল নিজের কোম্পানির **একাধিক শাখায়** অ্যাক্সেস পেতে পারে (শাখা সুইচার / শাখা-স্কোপ)।

**ধাপ D — অ্যাকাউন্টিং মডিউল টেস্ট (Demo Business-এ, Super Admin দিয়ে)**

9. মাস্টার ডেটা হাতে বানান: **Warehouse** (সক্রিয়), Customer `Alpha Traders`, Supplier `Omega Supplies`,
   Product `Widget A` (ট্র্যাক-ইনভেন্টরি সহ, খরচ ১০০/বিক্রয় ১৫০)।
10. **Purchase Bill** → ড্রাফট → Post → স্টক বাড়ে (On-hand 20 @ 100)। **Journals**-এ PUR জার্নাল যাচাই।
11. **Sales Invoice** → ড্রাফট → Post → স্টক কমে, AR বাড়ে, COGS জার্নাল। **Record Payment** → Paid।
12. **Supplier Payment** (বহু-বিল) → PMT জার্নাল। **Outstanding** উভয় দিকে `0.00`।
13. **Expense** (ক্যাশ/ব্যাংক/পেয়েবল) + **Cash & Bank** → ব্যালেন্স মেলান। **Stock Adjustment / Transfer**
    → ADJ/TR। **Fixed Asset** → capitalize → **Run Depreciation** → DEP। **Payroll** → run → post → payment।
14. **Budget** → ড্রাফট → Post → **Reports** (GL + Trial Balance) ও **Statements** (P&L/Balance Sheet/Cash Flow)।
15. **Approval Workflow** নিয়ম বানিয়ে গেটেড পোস্ট → **Notifications**-এ রিকোয়েস্ট → approve → পোস্ট সম্পন্ন।
16. **Period Closing** → অগ্রিম পিরিয়ড বন্ধ → পোস্ট ব্লক হয়; **Year-End Closing** → RE + ক্যারি-ফরোয়ার্ড OB।
17. **Audit Log** (+ ডকুমেন্ট অ্যাটাচমেন্ট) — প্রতিটি পরিবর্তন লগ হয়েছে দেখুন।

> প্রতিটি ধাপের বিস্তারিত হাতে-কলমে নির্দেশনা নিচের **"হাতে-কলমে ডেটা এন্ট্রি টেস্ট গাইড"** অধ্যায়ে আছে।

---

# PHASE 1 — কোম্পানি ও সিস্টেম সেটআপ

**মডিউল:** ০১. Company Management, ০২. Branch Management, ০৩. User & Role Management, ৩৩. System Settings

## ইউজার জার্নি

```
System Super Admin (প্রোডাক্ট মালিক) Login
  → Organization → Companies → + New Company
      → টেন্যান্ট ডেটাবেস + কোম্পানির সুপার অ্যাডমিন (মালিক) + Company Admin অটো-তৈরি
  → Company Admin Login → সব মেনু testing-ready
      → Branches → শাখা তৈরি → Users → ইউজার তৈরি + রোল + শাখা অ্যাক্সেস
  → নতুন ইউজার Login → রোল অনুযায়ী মেনু
```

## বিস্তারিত ওয়ার্কফ্লো

প্রোডাক্টটি **মাল্টি-টেন্যান্ট**: কোম্পানি তৈরি হয় প্রোডাক্ট মালিক (System Super Admin) দিয়ে।

১. মালিক `Organization → Companies → + New Company`-এ কোম্পানি তৈরি করেন: নাম, লিগ্যাল নাম, লোগো
   (আপলোড), দেশ, ঠিকানা, যোগাযোগ, বেস কারেন্সি, ট্যাক্স/ভ্যাট রেজিস্ট্রেশন নম্বর, অ্যাকাউন্টিং বেসিস
   (Accrual/Cash)।
২. **Save**-এর সঙ্গে সঙ্গেই সিস্টেম: (ক) কোম্পানির জন্য **আলাদা টেন্যান্ট ডেটাবেস** তৈরি করে সেখানে
   মাইগ্রেট + মাস্টার-ডেটা সিড করে; (খ) মালিককে সেই কোম্পানির **সুপার অ্যাডমিন** হিসেবে টেন্যান্টে কপি করে;
   (গ) **Company Admin** লগইন তৈরি করে (`admin@<company>.local` / `password`) — কোম্পানি তালিকায় ফ্ল্যাশ
   মেসেজে দেখানো হয়।
৩. **Company Admin** দিয়ে লগইন করলে **সব মেনু খোলা** থাকে (company-admin রোলে সব পারমিশন `*`) — ফলে
   কোম্পানির সব মডিউল নিজে টেস্ট করা যায়। এখানেই চলবে কোম্পানির দৈনন্দিন ব্যবস্থাপনা।
৪. `Organization → Branches → + Create Branch`: শাখার কোড, নাম, ঠিকানা, যোগাযোগ, শাখা ম্যানেজার (ড্রপডাউন
   থেকে ইউজার — না থাকলে খালি রাখা যায়)।
৫. `Organization → Users → + New User`: নাম/ইমেইল/ফোন/পাসওয়ার্ড + **রোল** (Accountant, Sales Executive,
   Viewer…) + Edit পেজে **কোন কোন শাখায়** অ্যাক্সেস থাকবে। একজন ইউজার **একটি কোম্পানির** হয়ে থাকে এবং
   ওই কোম্পানির ভেতরে **একাধিক শাখায়** অ্যাক্সেস পেতে পারে।
৬. নতুন ইউজার লগইন করলে মেনু/অ্যাকশন ঠিক **রোলের পারমিশন অনুযায়ী** আসে — তার বেশি নয় (মেনু
   ফিল্টার + রুট-লেভেল `permission:` মিডলওয়্যার দুই দিক থেকেই গেট করা)।
৭. `Organization → Roles & Permissions`-এ Company Admin নতুন রোল + কাস্টম পারমিশন-সেটও বানাতে
   পারেন — প্রি-বিল্ট রোল (Company Admin, Accountant, Viewer…) সিডে আছে।

## স্ক্রিন / নেভিগেশন

- `Organization → Companies` *(শুধু System Super Admin-এর জন্য — `superAdminOnly`)*
- `Organization → Branches`
- `Organization → Users`
- `Organization → Roles & Permissions`

## অ্যাকশন

Create, Edit, Deactivate (Company/Branch/User), Create User, Assign Role, Assign Branch Access, Revoke Access।

## বিজনেস রুল

- একটি কোম্পানি ছাড়া কোনো Branch, User বা পরবর্তী কোনো ডেটা তৈরি করা যায় না; কোম্পানি তৈরিতেই
  টেন্যান্ট + Company Admin অটো-তৈরি হয়।
- **একটি ইউজার = একটি কোম্পানি।** প্রতিটি ইউজারের একটি হোম কোম্পানি (`company_id`) থাকে; ইমেইল
  সারা প্ল্যাটফর্মে **unique** — তাই একই ইউজার কখনো দুটি কোম্পানিতে ডুপ্লিকেট হয় না।
- ওই এক কোম্পানির ভেতরেই ইউজার **একাধিক শাখায়** (branch) অ্যাক্সেস পেতে পারে — শাখা-স্কোপড
  অ্যাক্সেস `user_company_access.branch_id` / `user_roles.branch_id`-তে থাকে; সক্রিয় শাখা
  সুইচারের মাধ্যমে প্রেক্ষাপট বদল হয়।
- **Company Admin** = কোম্পানির পূর্ণ অ্যাক্সেস (সব পারমিশন `*`): সব মেনু, ইউজার/রোল/শাখা
  ব্যবস্থাপনা; তবে platform-স্তরের **Companies** তালিকা ও সেটিংস শুধু System Super Admin-এর —
  Company Admin সেগুলো দেখতে পায় না (sidebar-এও আসে না)।
- System Super Admin প্রতিটি টেন্যান্টেই নিজের **সুপার-অ্যাডমিন ক্ষমতা** পায় (টেন্যান্ট-কপি);
  permission মিডলওয়্যার তার জন্য bypass।
- রোল/পারমিশন **কোম্পানি-স্কোপড**: এক কোম্পানিতে দেওয়া রোল অন্য কোম্পানিতে কোনো ক্ষমতা দেয় না।

## অ্যাকাউন্টিং ইমপ্যাক্ট

এই ফেজে কোনো সরাসরি অ্যাকাউন্টিং এন্ট্রি তৈরি হয় না — এটি সম্পূর্ণ সাংগঠনিক (organizational) সেটআপ।

## পরবর্তী ওয়ার্কফ্লো

কোম্পানি ও ইউজার প্রস্তুত হওয়ার পর ব্যবহারকারী **Phase 2 — Accounting Foundation**-এ যান, যেখানে প্রকৃত অ্যাকাউন্টিং কাঠামো তৈরি হবে।

---

# PHASE 2 — অ্যাকাউন্টিং ফাউন্ডেশন

**মডিউল:** ০৪. Accounting Configuration, ০৫. Fiscal Year & Period, ০৬. Currency, ০৭. Tax/VAT, ০৮. Chart of Accounts

## ইউজার জার্নি

```
Company Setup → Fiscal Year → Accounting Periods → Base Currency
→ Additional Currencies → Exchange Rates → Tax/VAT Configuration
→ Chart of Accounts → Default Accounting Accounts → Accounting Rules
→ Ready for Transactions
```

## বিস্তারিত ওয়ার্কফ্লো

### Fiscal Year ও Period

1. Accountant/Company Admin `Accounting → Fiscal Years → + Create Fiscal Year` এ যান।
2. ফর্মে দেন: নাম (যেমন "FY 2026-27"), শুরুর তারিখ, শেষের তারিখ। **Save** করলে সিস্টেম স্বয়ংক্রিয়ভাবে ১২টি মাসিক **Accounting Period** তৈরি করে দেয় (প্রতিটি Period-এর স্ট্যাটাস শুরুতে "Open")।
3. একটি Fiscal Year তালিকায় ব্যবহারকারী প্রতিটি মাসের পাশে স্ট্যাটাস ব্যাজ দেখেন — Open / Closed / Locked।

### Currency

4. `Settings → Currencies` এ গিয়ে Base Currency নির্ধারণ করা হয় (এটি একবার সেট হলে সাধারণত পরিবর্তনযোগ্য নয়)। প্রয়োজনে অতিরিক্ত কারেন্সি (USD, EUR ইত্যাদি) যোগ করা যায়।
5. `Currencies → Exchange Rates` এ গিয়ে প্রতিটি কারেন্সির জন্য তারিখভিত্তিক এক্সচেঞ্জ রেট এন্ট্রি করা হয় — যেমন 1 USD = 118.50 BDT (effective from তারিখ)। বিদেশি মুদ্রায় কোনো লেনদেন হলে সিস্টেম সবসময় সর্বশেষ কার্যকর রেট ব্যবহার করে, যদি না ব্যবহারকারী ম্যানুয়ালি ওভাররাইড করেন।

### Tax/VAT

6. `Settings → Tax/VAT → + Create Tax Type` — যেমন "VAT", "AIT"। প্রতিটি Tax Type-এর অধীনে একাধিক **Tax Rate** যোগ করা হয় (যেমন 15%, 5%, 0%), সাথে ইনপুট ট্যাক্স ও আউটপুট ট্যাক্স অ্যাকাউন্ট নির্বাচন করা হয় (যা Chart of Accounts থেকে আসে — তাই Tax Setup সাধারণত CoA তৈরি হওয়ার পরে সম্পূর্ণ হয়)।
7. প্রতিটি Tax Rate-এ টগল থাকে — Tax Inclusive নাকি Exclusive।

### Chart of Accounts (মূল মডিউল)

8. `Accounting → Chart of Accounts` এ ব্যবহারকারী একটি ট্রি-স্টাইল তালিকা দেখেন:

```
Asset → Current Assets → Cash
Asset → Current Assets → Accounts Receivable
Asset → Fixed Assets → Computer Equipment
Liability → Current Liabilities → Accounts Payable
Equity → Capital
Income → Sales Revenue
Expense → Salary Expense
```

   হায়ারার্কি তিন স্তরে বিভক্ত: **Account Group (Level 1)** → **Sub Group (Level 2)** → **Ledger Account (Level 3, লেনদেন-যোগ্য)**।
9. **নতুন অ্যাকাউন্ট তৈরি:** ব্যবহারকারী `+ Create Account` ক্লিক করেন → ফর্মে দেন: Account Name, Account Name (Bangla), Account Type (Asset/Liability/Equity/Income/Expense), Parent Account (ড্রপডাউন/সার্চ), Normal Balance (স্বয়ংক্রিয়ভাবে Account Type অনুযায়ী সাজেস্ট হয়)। Account Code স্বয়ংক্রিয়ভাবে জেনারেট হয় (প্যারেন্টের কোডের ওপর ভিত্তি করে), তবে ম্যানুয়ালি ওভাররাইডও করা যায়।
10. **অ্যাকাউন্ট এডিট:** নাম, বর্ণনা পরিবর্তন করা যায় সবসময়; কিন্তু Account Type বা Parent পরিবর্তন করলে সিস্টেম সতর্কতা দেখায় যদি ওই অ্যাকাউন্টে ইতিমধ্যে লেনদেন থাকে।
11. **ডিঅ্যাক্টিভেট:** যে অ্যাকাউন্টে লেনদেন আছে তা ডিলিট করা যায় না — শুধু "Inactive" করা যায়, যাতে নতুন লেনদেনে এটি নির্বাচন করা না যায়, কিন্তু পুরনো ইতিহাস অক্ষত থাকে।
12. **অ্যাকাউন্ট সার্চ/নির্বাচন:** যেকোনো ফর্মে "Account Selector" কম্পোনেন্ট থাকে — টাইপ করলে নাম/কোড দিয়ে অটোকমপ্লিট সাজেশন আসে, এবং **শুধুমাত্র লেজার-লেভেল (postable) অ্যাকাউন্ট** দেখানো হয় — গ্রুপ-লেভেল অ্যাকাউন্ট (যেমন "Current Assets") এখানে নির্বাচনযোগ্য নয়।
13. **অ্যাকাউন্ট ডিটেইলস → লেজার:** তালিকায় কোনো অ্যাকাউন্টের নামে ক্লিক করলে তার Details পেজ খোলে, যেখানে ট্যাব থাকে — Overview, **Ledger** (তারিখ অনুযায়ী সব ডেবিট/ক্রেডিট এন্ট্রি ও রানিং ব্যালেন্স), এবং সংশ্লিষ্ট Journal-এ ড্রিল-ডাউন করার লিংক।
14. **Accounting Configuration:** `Settings → Accounting Configuration` এ গিয়ে Default Sales Account, Default Purchase Account, Default Inventory Account, Default AR Account, Default AP Account, Default Cash/Bank Account, Default Tax Input/Output Account নির্ধারণ করা হয় — এই ডিফল্টগুলো পরবর্তীতে প্রতিটি Sales Invoice, Purchase Bill ইত্যাদির জন্য অ্যাকাউন্ট অটো-সাজেস্ট করবে যাতে সাধারণ ব্যবহারকারীকে প্রতিবার ম্যানুয়ালি অ্যাকাউন্ট বাছাই করতে না হয়।

## বিজনেস রুল

- Fiscal Year ছাড়া কোনো Period তৈরি হয় না; Period ছাড়া কোনো Journal পোস্ট করা যায় না।
- শুধু Level-3 (লেজার) অ্যাকাউন্ট লেনদেনে ব্যবহারযোগ্য — গ্রুপ/সাব-গ্রুপ শুধু রিপোর্টিং কাঠামোর জন্য।
- Account Code প্রতিটি কোম্পানির মধ্যে ইউনিক হতে হবে।

## পরবর্তী ওয়ার্কফ্লো

অ্যাকাউন্টিং ফাউন্ডেশন প্রস্তুত হলে ব্যবহারকারী **Phase 3 — Master Data**-এ যান, যেখানে Customer, Supplier, Product ও Warehouse তৈরি হবে — এগুলোর প্রতিটি Chart of Accounts-এর সাথে সংযুক্ত থাকবে (যেমন প্রতিটি Product-এ Sales/Purchase/Inventory/COGS অ্যাকাউন্ট বাছাই করতে হবে)।

---

# PHASE 3 — মাস্টার ডেটা

**মডিউল:** ০৯. Customer, ১০. Supplier, ১১. Product/Service, ১২. Warehouse

## Customer

```
Customer List → Create Customer → Customer Details
→ Opening Balance → Credit Terms → Customer Ledger
→ Customer Statement → Sales / Receipts
```

1. `Sales → Customers → Customer List` এ ব্যবহারকারী সব গ্রাহকের একটি টেবিল দেখেন — সার্চ, ফিল্টার (Active/Inactive), এবং পেজিনেশনসহ।
2. `+ Create Customer` ক্লিক করলে একটি ফর্ম/ড্রয়ার খোলে যেখানে দেওয়া হয়: Customer Name, Code (অটো-জেনারেটেড), Phone, Email, Address, Tax/VAT নম্বর, Credit Limit, Payment Terms (দিন সংখ্যা, যেমন "Net 30"), Opening Balance (Debit/Credit)।
3. **Save** করার পর সিস্টেম Validation করে (ইমেইল ফরম্যাট, ডুপ্লিকেট কোড ইত্যাদি), সফল হলে একটি Success Message দেখায় এবং সরাসরি **Customer Details** পেজে নিয়ে যায়।
4. Customer Details পেজে ট্যাব থাকে: **Overview** (মূল তথ্য), **Transactions** (সব ইনভয়েস/রিসিট একসাথে), **Ledger** (ডেবিট-ক্রেডিট এন্ট্রি ও রানিং ব্যালেন্স), **Statement** (নির্দিষ্ট তারিখ-রেঞ্জের জন্য প্রিন্ট-যোগ্য স্টেটমেন্ট), **Invoices**, **Payments**।
5. এই গ্রাহকের নামে যদি Opening Balance দেওয়া হয়ে থাকে, তা সরাসরি Ledger ট্যাবে "Opening Balance" এন্ট্রি হিসেবে দেখা যাবে।

## Supplier

```
Supplier List → Create Supplier → Supplier Details
→ Opening Balance → Payment Terms → Supplier Ledger
→ Supplier Statement → Purchases / Payments
```

Customer-এর মতো একই প্যাটার্ন — `Purchase → Suppliers`, একই রকম ফর্ম ও ডিটেইলস-ট্যাব কাঠামো, শুধু Credit Limit-এর বদলে Payment Terms এবং AR-এর বদলে AP অ্যাকাউন্টের সাথে সংযুক্ত।

## Product / Service

```
Product List → Create Product/Service → Pricing → Tax
→ Accounting Accounts → Inventory Configuration → Product Details
```

1. `Inventory → Products → + Create Product` এ ব্যবহারকারী প্রথমে Type নির্বাচন করেন — **Product** নাকি **Service**।
2. Product হলে ফর্মে থাকে: SKU, নাম, Category, Unit, Purchase Price, Sales Price, Tax Rate, এবং **Accounting Accounts** সেকশনে — Sales Account, Purchase Account, Inventory Account, COGS Account (ডিফল্ট Accounting Configuration থেকে অটো-পূরণ হয়, প্রয়োজনে ওভাররাইড করা যায়)। **Inventory Configuration** সেকশনে Track Inventory চালু থাকে, ব্যাচ/সিরিয়াল/এক্সপায়ারি ট্র্যাকিং টগল করা যায়, এবং প্রাথমিক Opening Stock (ঐচ্ছিক, Phase 4-এ পূর্ণভাবে হ্যান্ডেল হয়) দেওয়া যায়।
3. Service হলে Inventory Configuration সেকশন লুকানো থাকে — কোনো স্টক ট্র্যাক হবে না, বিক্রি হলে সরাসরি Sales Revenue-এ পোস্ট হবে, কোনো COGS/Inventory এন্ট্রি হবে না।
4. Product Details পেজে ট্যাব: Overview, Pricing History, Stock (শুধু Product-এর জন্য), Sales History, Purchase History।

## Warehouse

```
Warehouse List → Create Warehouse → Assign Branch
→ Configure Warehouse → View Stock
```

1. `Inventory → Warehouses → + Create Warehouse` — কোড, নাম, ঠিকানা, শাখা (ড্রপডাউন থেকে), ওয়্যারহাউস ম্যানেজার নির্বাচন করা হয়।
2. Warehouse Details পেজে "View Stock" ট্যাবে বর্তমান সব Product-এর স্টক পরিমাণ ও মূল্য দেখা যায়, ওয়্যারহাউস-ভিত্তিক ফিল্টার সহ।

## অ্যাকাউন্টিং ইমপ্যাক্ট

মাস্টার ডেটা তৈরি নিজে থেকে কোনো Journal তৈরি করে না — Opening Balance দেওয়া থাকলে তা Phase 4-এ একত্রে পোস্ট হয়।

## পরবর্তী ওয়ার্কফ্লো

মাস্টার ডেটা সম্পূর্ণ হলে ব্যবহারকারী **Phase 4 — Opening Balances**-এ যান, অথবা সরাসরি Phase 5/6-এ গিয়ে নতুন লেনদেন শুরু করতে পারেন যদি কোম্পানিটি সম্পূর্ণ নতুন (কোনো পূর্ব ইতিহাস নেই) হয়।

---

# PHASE 4 — ওপেনিং ব্যালেন্স ও প্রাথমিক অবস্থা

এই ফেজ তখন প্রযোজ্য যখন একটি বাস্তব-জগতের কোম্পানি (যার আগে থেকেই হিসাব চলছিল) নতুন করে এই সিস্টেমে প্রবেশ করছে।

## ইউজার জার্নি

```
Chart of Accounts Ready → Opening Balance Entry
→ Account-wise Opening → Customer Opening → Supplier Opening
→ Cash/Bank Opening → Inventory Opening → Fixed Asset Opening
→ Validate (Total Debit = Total Credit) → Confirm → Post
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Accounting → Opening Balances → + Enter Opening Balances` এ গিয়ে ব্যবহারকারী প্রথমে Fiscal Year নির্বাচন করেন (যে বছর থেকে সিস্টেম চালু হচ্ছে)।
2. একটি টেবিল-স্টাইল ফর্ম খোলে, যেখানে প্রতিটি সারিতে একটি Account (Cash, Bank, Accounts Receivable, Accounts Payable, Inventory, Fixed Assets, Capital ইত্যাদি) থাকে এবং তার পাশে Debit/Credit কলাম — ব্যবহারকারী প্রতিটি অ্যাকাউন্টের প্রকৃত ব্যালেন্স লিখে দেন।
3. **Customer/Supplier Opening Balance** আলাদা সেকশনে হ্যান্ডেল হয় (এগুলো Phase 3-এ কাস্টমার/সাপ্লায়ার তৈরির সময়ও দেওয়া যায়) — প্রতিটি গ্রাহক/সরবরাহকারীর জন্য পৃথক Opening Balance এন্ট্রি, যা মোট AR/AP কন্ট্রোল অ্যাকাউন্টের সাথে মিলতে হবে।
4. **Inventory Opening:** প্রতিটি Product-এর জন্য Warehouse-ভিত্তিক Opening Quantity ও Unit Cost দেওয়া হয় — এটি Inventory অ্যাকাউন্টের Opening Balance-এর সাথে টাই করতে হবে।
5. **Fixed Asset Opening:** যদি কোম্পানির আগে থেকেই সম্পদ থাকে, তবে প্রতিটি Asset-এর Acquisition Cost, Accumulated Depreciation পর্যন্ত এন্ট্রি করা হয়।
6. সাবমিট করার আগে সিস্টেম একটি **সামারি স্ক্রিন** দেখায়: Total Debit বনাম Total Credit — যদি দুটি না মেলে, "Confirm" বাটন disabled থাকে এবং লাল অক্ষরে পার্থক্যের পরিমাণ দেখানো হয়।
7. দুটি মিললে ব্যবহারকারী **Confirm & Post** ক্লিক করেন → সিস্টেম একটি বিশেষ "Opening Balance Journal" তৈরি করে posted স্ট্যাটাসে, যার তারিখ Fiscal Year শুরুর দিন।

## অ্যাকাউন্টিং ইমপ্যাক্ট

Opening Balance পোস্ট হওয়ার সাথে সাথে তা তাৎক্ষণিকভাবে দেখা যাবে:

- **General Ledger / Account Ledger** — প্রতিটি অ্যাকাউন্টের প্রথম এন্ট্রি হিসেবে।
- **Trial Balance** — শুরুর ব্যালেন্স হিসেবে।
- **Balance Sheet** — Assets/Liabilities/Equity-এর প্রাথমিক অবস্থান হিসেবে।
- **Customer/Supplier Statement** — "Opening Balance b/f" লাইন হিসেবে।
- **Inventory Reports** — Opening Stock হিসেবে।

## বিজনেস রুল

- একবার Confirm & Post হয়ে গেলে Opening Balance Journal সরাসরি এডিট করা যায় না — সংশোধনের জন্য Adjustment Journal ব্যবহার করতে হয়।
- প্রতিটি Fiscal Year-এ একবারই Opening Balance পোস্ট করা যায়।

## পরবর্তী ওয়ার্কফ্লো

এখন সিস্টেম দৈনন্দিন লেনদেনের জন্য প্রস্তুত — **Phase 5 (Sales)**, **Phase 6 (Purchase)** ইত্যাদি শুরু হতে পারে।

---

# PHASE 5 — সেলস ওয়ার্কফ্লো

**মডিউল:** ১৪. Sales

## ইউজার জার্নি

```
Quotation → Sales Order → Delivery → Sales Invoice → Payment
→ Receipt → Customer Ledger → Accounts Receivable → Financial Reports
```

## বিস্তারিত ওয়ার্কফ্লো

### ধাপ ১ — Quotation (ঐচ্ছিক)

ব্যবহারকারী `Sales → Quotations → + New Quotation` এ যান। Customer, Product/Service, পরিমাণ, একক মূল্য, Discount নির্বাচন করেন। **Save as Draft** বা **Send to Customer** করা যায়। কোনো অ্যাকাউন্টিং প্রভাব নেই। গ্রাহক রাজি হলে **Convert to Sales Order** বাটনে ক্লিক করলে সব তথ্য অটো-কপি হয়ে নতুন Sales Order তৈরি হয়।

### ধাপ ২ — Sales Order

`Sales → Orders` এ Order তৈরি/দেখা যায়। স্ট্যাটাস: Draft → Confirmed। Confirm করলে Product-এর স্টকে "Reserved/Committed" হিসেবে চিহ্নিত হয় (এখনও প্রকৃত স্টক কমে না)।

### ধাপ ৩ — Delivery Note

`Sales → Deliveries → + Create Delivery` — Order থেকে "Create Delivery" ক্লিক করলে লাইন-আইটেম অটো-পূরণ হয়, ব্যবহারকারী Warehouse নির্বাচন করেন, প্রকৃত পাঠানো পরিমাণ কনফার্ম করেন (আংশিক ডেলিভারিও সম্ভব)। Delivery Confirm করলে **প্রকৃত স্টক কমে যায়** (Stock Movement তৈরি হয়), কিন্তু এখনো কোনো Journal পোস্ট হয় না যদি না কোম্পানির Accounting Policy "Delivery-এ COGS বুক করা" নির্ধারিত থাকে (ডিফল্টে Invoice-এ COGS বুক হয়)।

### ধাপ ৪ — Sales Invoice (মূল অ্যাকাউন্টিং লেনদেন)

1. ব্যবহারকারী `Sales → Invoices → + Create Invoice` এ যান, অথবা Delivery/Order থেকে **"Convert to Invoice"** করেন।
2. ফর্মে: Customer (সিলেক্ট করলে Credit Limit ও বকেয়া দেখা যায়), Invoice Date, Due Date (Payment Terms থেকে অটো-ক্যালকুলেটেড), Product Line (প্রতিটি লাইনে Product, Quantity, Unit Price, Discount, Tax Rate — Subtotal/Tax/Total অটো-ক্যালকুলেট)।
3. **Save as Draft** করলে স্ট্যাটাস "Draft" — এডিটযোগ্য, কোনো অ্যাকাউন্টিং প্রভাব নেই।
4. **Submit** করলে স্ট্যাটাস "Submitted" হয়ে অ্যাপ্রুভাল ওয়ার্কফ্লোতে যায় (যদি অ্যাপ্রুভাল রুল সক্রিয় থাকে — যেমন নির্দিষ্ট পরিমাণের ওপরে ম্যানেজার অ্যাপ্রুভাল লাগবে)।
5. অনুমোদিত হলে স্ট্যাটাস "Approved" হয়, এরপর **Post** বাটনে ক্লিক করলে (বা অটো-পোস্ট কনফিগার থাকলে সাথে সাথে) সিস্টেম:
   - Invoice-কে "Posted" স্ট্যাটাস দেয়, Invoice Number স্থায়ীভাবে বরাদ্দ করে।
   - একটি Journal তৈরি করে: `Accounts Receivable Dr | Sales Revenue Cr | Tax Payable Cr`।
   - Inventory জড়িত থাকলে অতিরিক্ত লাইন: `Cost of Goods Sold Dr | Inventory Cr` (FIFO/Weighted Average অনুযায়ী গণনাকৃত)।
   - Customer-এর বকেয়া ব্যালেন্স বৃদ্ধি পায়, Customer Ledger-এ নতুন এন্ট্রি যোগ হয়।
   - Product-এর স্টক (যদি Delivery-তে না কমে থাকে) এখন কমে যায়।
6. Posted Invoice-এ একটি **"View Journal"** লিংক থাকে — ক্লিক করলে সংশ্লিষ্ট Journal Entry-তে সরাসরি নিয়ে যায়।

### ধাপ ৫ — Payment / Receipt

1. Invoice Details পেজ থেকে **"Record Payment"** ক্লিক করলে একটি মোডাল খোলে: Payment Date, Cash/Bank Account, Amount।
2. **আংশিক পেমেন্ট:** Amount < বকেয়া হলে Invoice স্ট্যাটাস "Partially Paid" হয়, বকেয়া কমে।
3. **পূর্ণ পেমেন্ট:** Amount = বকেয়া হলে স্ট্যাটাস "Paid" হয়।
4. **ওভারপেমেন্ট:** Amount > বকেয়া হলে সিস্টেম সতর্ক করে এবং জিজ্ঞাসা করে — অতিরিক্ত অর্থ কি "Customer Advance" হিসেবে রাখা হবে, নাকি অন্য কোনো Invoice-এ বরাদ্দ করা হবে (Receipt Allocation স্ক্রিনে একাধিক ইনভয়েসে ভাগ করে দেওয়া যায়)।
5. Receipt পোস্ট হলে Journal: `Cash/Bank Dr | Accounts Receivable Cr`।

### বিশেষ ফ্লো

- **Invoice Cancellation:** শুধু "Draft" স্ট্যাটাসে থাকা Invoice সরাসরি Cancel করা যায়।
- **Invoice Void:** Posted Invoice-এ "Void" অপশন থাকে (উপযুক্ত পারমিশনসহ) — এটি মূল এন্ট্রি না মুছে একটি বিপরীত (reversing) Journal তৈরি করে, Invoice-এর স্ট্যাটাস "Voided" দেখায়।
- **Sales Return / Credit Note:** Posted Invoice থেকে **"Create Credit Note"** — ফেরত দেওয়া Product ও পরিমাণ উল্লেখ করলে Journal: `Sales Revenue Dr | Accounts Receivable Cr` (এবং ইনভেন্টরি ফেরত জমা হলে `Inventory Dr | COGS Cr`)। Credit Note গ্রাহকের বকেয়ার বিপরীতে সমন্বয় করা যায় বা নগদ ফেরত দেওয়া যায়।
- **Discount/Tax:** লাইন-লেভেল বা ইনভয়েস-লেভেল Discount দেওয়া যায়; Tax স্বয়ংক্রিয়ভাবে Product-এর ডিফল্ট Tax Rate থেকে আসে, ম্যানুয়ালি পরিবর্তনযোগ্য।
- **Overdue Invoice:** Due Date পার হয়ে গেলে Invoice তালিকায় "Overdue" ব্যাজ (লাল) দেখায়, ড্যাশবোর্ডে ও AR Aging রিপোর্টে দেখা যায়, এবং Notification পাঠানো হয়।

## পরবর্তী ওয়ার্কফ্লো

প্রতিটি Posted Invoice সরাসরি **Phase 8 — Accounts Receivable** ও **Phase 18 — Financial Statements**-এ প্রতিফলিত হয়।

---

# PHASE 6 — পারচেজ ওয়ার্কফ্লো

**মডিউল:** ১৫. Purchase

## ইউজার জার্নি

```
Purchase Requisition → Purchase Order → Goods Receive
→ Purchase Bill → Payment → Supplier Ledger → Accounts Payable
```

## বিস্তারিত ওয়ার্কফ্লো

Sales-এর সমান্তরাল প্যাটার্ন, Purchase-এর দিক থেকে।

1. **Requisition (ঐচ্ছিক):** অভ্যন্তরীণ অনুরোধ, Approval প্রয়োজন হতে পারে, কোনো অ্যাকাউন্টিং প্রভাব নেই।
2. **Purchase Order:** `Purchase → Orders → + Create PO` — Supplier, Product, পরিমাণ, ইউনিট কস্ট নির্বাচন করে সাপ্লায়ারকে পাঠানো হয়। কোনো অ্যাকাউন্টিং প্রভাব নেই।
3. **Goods Received Note (GRN):** `Purchase → Goods Receive → + Create GRN` — PO থেকে কনভার্ট করলে লাইন অটো-পূরণ হয়; প্রকৃত প্রাপ্ত পরিমাণ কনফার্ম করা হয় (**আংশিক প্রাপ্তি** সম্ভব — বাকিটা "Pending" থাকে PO-তে)। GRN কনফার্ম হলে Product-এর স্টক বৃদ্ধি পায় (Stock Movement তৈরি হয়), কিন্তু এখনো Journal পোস্ট হয় না যদি Purchase Bill এখনো না আসে (এই অবস্থায় সিস্টেম একটি অস্থায়ী "GRN-not-billed" লায়াবিলিটি ট্র্যাক করে, যা Bill আসার পর মূল Purchase Journal-এ মার্জ হয়)।
4. **Purchase Bill:** `Purchase → Bills → + Create Bill` অথবা GRN থেকে **"Convert to Bill"**। Bill Number, Bill Date, Due Date, Tax, Discount দেওয়া হয়। **আংশিক বিলিং** সম্ভব — একাধিক GRN একত্রে একটি Bill-এ আসতে পারে, অথবা একটি GRN-এর অংশ বিল হতে পারে।
5. Bill Post হলে Journal: `Inventory (বা Expense) Dr | Input Tax Dr | Accounts Payable Cr`।
6. **Supplier Payment:** Bill Details থেকে **"Record Payment"** — আংশিক/পূর্ণ পেমেন্ট, একাধিক Bill-এ Allocation। Journal: `Accounts Payable Dr | Cash/Bank Cr`।

### বিশেষ ফ্লো

- **Purchase Return / Debit Note:** Posted Bill থেকে "Create Debit Note" — Journal: `Accounts Payable Dr | Inventory Cr`।
- **Cancellation/Void:** Draft বাতিলযোগ্য; Posted Bill শুধু Void/Reverse করা যায়, ডিলিট নয়।
- **Overdue Bill:** Aging রিপোর্ট ও ড্যাশবোর্ডে "Overdue" হিসেবে চিহ্নিত হয়, Notification পাঠানো হয়।
- **Approval:** নির্দিষ্ট পরিমাণের ওপরে PO/Bill/Payment-এর জন্য অ্যাপ্রুভাল ধাপ বাধ্যতামূলক করা যায় (Phase 16 দেখুন)।

## পরবর্তী ওয়ার্কফ্লো

Posted Purchase Bill সরাসরি **Phase 7 — Inventory** (স্টক বৃদ্ধি) ও **Phase 9 — Accounts Payable**-এ প্রতিফলিত হয়।

---

# PHASE 7 — ইনভেন্টরি ওয়ার্কফ্লো

**মডিউল:** ১৩. Inventory

## ইউজার জার্নি

```
Opening Stock → Purchase Receive → Stock → Stock Transfer
→ Stock Adjustment → Sales Delivery → Sales Return → Stock Valuation
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Inventory → Stock` এ ব্যবহারকারী প্রতিটি Product-এর বর্তমান স্টক (সব Warehouse মিলিয়ে, বা Warehouse-ভিত্তিক ফিল্টার করে) দেখতে পান — কলামে থাকে: Product, Warehouse, Available Qty, Reserved Qty, Unit Cost, Total Value। **Low Stock** থ্রেশহোল্ডের নিচে থাকা প্রোডাক্ট লাল রঙে হাইলাইট হয়।
2. **Stock Adjustment:** `Inventory → Adjustments → + New Adjustment` — Product, Warehouse, বর্তমান সিস্টেম-কোয়ান্টিটি (অটো-দেখানো) বনাম প্রকৃত গণনাকৃত কোয়ান্টিটি লিখে পার্থক্য এন্ট্রি করা হয়, সাথে Reason (Damage, Theft, Count Correction ইত্যাদি)। Approval প্রয়োজন হতে পারে। Post হলে Journal: `Inventory Dr/Cr | Inventory Adjustment Expense Cr/Dr`।
3. **Stock Transfer:** `Inventory → Transfers → + New Transfer` — Source Warehouse, Destination Warehouse, Product, Quantity। কোনো সাধারণ অ্যাকাউন্টিং প্রভাব নেই (একই Inventory অ্যাকাউন্টের মধ্যে স্থানান্তর), কিন্তু Warehouse-ভিত্তিক স্টক রিপোর্টে প্রতিফলিত হয়।
4. **Batch/Serial/Expiry:** Product Configuration-এ চালু থাকলে, প্রতিটি Receive/Issue-এর সময় ব্যাচ নম্বর বা সিরিয়াল নম্বর ও মেয়াদ উত্তীর্ণের তারিখ বাধ্যতামূলকভাবে দিতে হয়। Expiry Report-এ মেয়াদ-নিকটবর্তী ব্যাচ আলাদাভাবে দেখানো হয়।
5. **Stock Valuation:** `Inventory → Valuation Report` এ FIFO বা Weighted Average (Product Configuration-এ নির্ধারিত পদ্ধতি অনুযায়ী) ভিত্তিতে বর্তমান স্টক মূল্য দেখা যায় — এই রিপোর্টের মোট মূল্য General Ledger-এর Inventory অ্যাকাউন্টের ব্যালেন্সের সাথে মিলতে হবে।

## অন্যান্য মডিউলের সাথে সংযোগ

- **Purchase (GRN)** → স্টক বৃদ্ধি → Bill Post হলে `Inventory Dr`।
- **Sales (Delivery)** → স্টক হ্রাস → Invoice Post হলে `COGS Dr | Inventory Cr`।
- **General Ledger** → সব Stock Movement-এর আর্থিক প্রভাব Journal-এ প্রতিফলিত হয়, Balance Sheet-এ Inventory as Current Asset দেখা যায়।

## পরবর্তী ওয়ার্কফ্লো

স্টক ও তার মূল্যায়ন **Phase 17 — Reports** (Inventory Reports) ও **Phase 18 — Financial Statements** (Balance Sheet)-এ ব্যবহৃত হয়।

---

# PHASE 8 — অ্যাকাউন্টস রিসিভেবল

**মডিউল:** ১৬. Accounts Receivable

## ইউজার জার্নি

```
Sales Invoice → Receivable → Due Date → Payment
→ Payment Allocation → Customer Balance → Customer Ledger
→ Statement → Aging Report
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Receivables → Outstanding` এ সব বকেয়া Invoice-এর তালিকা দেখা যায় — Customer, Invoice No, Invoice Date, Due Date, Amount, Balance Due, Days Overdue।
2. **Advance Payment:** কোনো Invoice ছাড়াই Customer থেকে অগ্রিম অর্থ গ্রহণ করা যায় (`Receivables → Advance Receipt`) — Journal: `Cash/Bank Dr | Customer Advance (Liability) Cr`। পরবর্তী Invoice তৈরি হলে এই Advance ম্যানুয়ালি বরাদ্দ করা যায়।
3. **একাধিক Invoice-এ একসাথে পেমেন্ট:** `Receivables → Record Payment` থেকে একজন Customer নির্বাচন করলে তার সব বকেয়া Invoice তালিকায় আসে, ব্যবহারকারী একটি মোট Amount দিয়ে একাধিক Invoice-এ Allocation ভাগ করে দিতে পারেন (অটো-অ্যালোকেট "oldest first" অপশনসহ)।
4. **Aging Report:** `Receivables → Aging Report` এ Customer-ভিত্তিক Current, 1-30, 31-60, 61-90, 90+ দিনের বাকেটে বকেয়া ভাগ করে দেখানো হয়, প্রতিটি সংখ্যায় ক্লিক করলে সংশ্লিষ্ট Invoice তালিকায় ড্রিল-ডাউন হয়।
5. **Write-off:** আদায়যোগ্য নয় এমন বকেয়া, উপযুক্ত অনুমোদনসহ, `Write-off` করা যায় — Journal: `Bad Debt Expense Dr | Accounts Receivable Cr`।

## AR ড্যাশবোর্ডে যা দেখা যায়

মোট Receivable, এই মাসে আদায় হওয়া অর্থ, Overdue পরিমাণ, শীর্ষ ৫ বকেয়া গ্রাহক — প্রতিটি সংখ্যা ক্লিক করলে বিস্তারিত রিপোর্টে যায়।

## পরবর্তী ওয়ার্কফ্লো

AR ডেটা সরাসরি **Balance Sheet**-এর Current Asset অংশে এবং **Cash Flow Statement**-এর Operating Activities-এ ব্যবহৃত হয়।

---

# PHASE 9 — অ্যাকাউন্টস পেয়েবল

**মডিউল:** ১৭. Accounts Payable

## ইউজার জার্নি

```
Purchase Bill → Payable → Due Date → Payment
→ Payment Allocation → Supplier Balance → Supplier Ledger
→ Statement → Aging Report
```

Phase 8-এর সম্পূর্ণ প্রতিসম রূপ — শুধু Customer-এর বদলে Supplier, Receipt-এর বদলে Payment, Debit Note সংযুক্ত থাকে Sales Return-এর বদলে। Advance Payment to Supplier, একাধিক Bill-এ একসাথে Payment Allocation, এবং AP Aging Report একই প্যাটার্নে কাজ করে।

---

# PHASE 10 — ক্যাশ ও ব্যাংক

**মডিউল:** ১৮. Cash & Bank

## ইউজার জার্নি

```
Cash Receipt → Cash Balance → Cash Ledger
Cash Payment → Cash Balance → Cash Ledger
Bank Deposit → Bank Balance
Bank Withdrawal → Bank Balance
Bank Transfer → Source Account → Destination Account
Bank Statement → Import → Match → Review → Reconcile → Reconciled Balance
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Cash & Bank → Cash → + New Receipt/Payment` — সরাসরি নগদ প্রাপ্তি/প্রদানের জন্য (Sales/Purchase সম্পর্কিত নয়, যেমন নগদ বিবিধ আয়/ব্যয়)। Journal: `Cash Dr | [Income/Payable] Cr` অথবা বিপরীত।
2. `Cash & Bank → Bank Accounts → + Add Bank Account` — ব্যাংকের নাম, শাখা, হিসাব নম্বর, কারেন্সি, সংশ্লিষ্ট GL Account দেওয়া হয়।
3. **Deposit/Withdrawal:** ব্যাংক অ্যাকাউন্ট থেকেই "Deposit" বা "Withdraw" বাটনে ক্লিক করে পরিমাণ ও তারিখ দেওয়া হয়।
4. **Bank Transfer:** `+ New Transfer` — Source Account ও Destination Account নির্বাচন করে একটি একক ফর্মে পরিমাণ দিলে সিস্টেম দুই পাশের এন্ট্রি নিজেই তৈরি করে: Journal: `Destination Account Dr | Source Account Cr`।
5. **Bank Charges/Interest:** Bank Account Details থেকে সরাসরি এন্ট্রি করা যায় — Journal: `Bank Charges Expense Dr | Bank Cr` অথবা `Bank Dr | Interest Income Cr`।

### Bank Reconciliation (ধাপে ধাপে)

1. Accountant `Cash & Bank → Reconciliation → + New Reconciliation` এ যান, ব্যাংক অ্যাকাউন্ট ও মাস নির্বাচন করেন।
2. **Import:** ব্যাংক থেকে ডাউনলোড করা CSV/Excel স্টেটমেন্ট আপলোড করেন। সিস্টেম প্রতিটি লাইন (তারিখ, বিবরণ, পরিমাণ) পার্স করে দেখায়।
3. **Match:** সিস্টেম স্বয়ংক্রিয়ভাবে তারিখ ও পরিমাণ মিলিয়ে সিস্টেমের Bank Transaction-এর সাথে ব্যাংক স্টেটমেন্ট লাইন ম্যাচ করার চেষ্টা করে; ম্যাচ হওয়া লাইনগুলো সবুজ চেকমার্ক দেখায়।
4. **Review:** অ-ম্যাচড লাইনগুলো (যেমন ব্যাংক চার্জ যা সিস্টেমে এখনো এন্ট্রি হয়নি) আলাদা তালিকায় দেখানো হয়, ব্যবহারকারী চাইলে সরাসরি সেখান থেকে "Create Transaction" ক্লিক করে নতুন এন্ট্রি বানিয়ে ম্যাচ করতে পারেন।
5. **Reconcile:** সব লাইন ম্যাচ হওয়ার পর "Complete Reconciliation" ক্লিক করলে সিস্টেম "Reconciled Balance" নিশ্চিত করে এবং তারিখ লক করে দেয় (এই তারিখের আগের ব্যাংক এন্ট্রি সাধারণত পরিবর্তনযোগ্য থাকে না)।

## পরবর্তী ওয়ার্কফ্লো

Reconciled Bank Balance সরাসরি **Balance Sheet** ও **Cash Flow Statement**-এ প্রতিফলিত হয়।

---

# PHASE 11 — এক্সপেন্স ম্যানেজমেন্ট

**মডিউল:** ১৯. Expense

## ইউজার জার্নি

```
Create Expense → Select Expense Account → Select Payment Method
→ Add Tax → Attach Document → Submit → Approve → Post
→ Accounting Entry → Expense Report
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Expenses → + New Expense` — Expense Category (ড্রপডাউন — প্রতিটির সাথে একটি ডিফল্ট Expense Account যুক্ত), Payee, তারিখ, Amount, Tax Rate, Payment Method (Cash/Bank/Payable — Payable বাছলে এটি সাথে সাথে না দিয়ে একটি Supplier Bill-এর মতো আচরণ করে)।
2. **Attach Document:** রিসিট/বিলের ছবি বা PDF আপলোড করা হয়, যা পরে অডিটের জন্য দরকার হবে।
3. **Submit → Approve → Post:** Approval Workflow-এর নিয়ম অনুযায়ী (উদাহরণ: ৳৫,০০০-এর বেশি হলে ম্যানেজার অনুমোদন লাগবে)।
4. Post হলে Journal: `Expense Account Dr | Input Tax Dr | Cash/Bank/Accounts Payable Cr`।
5. **Recurring Expense:** "Make Recurring" টগল করলে Frequency (মাসিক/সাপ্তাহিক) নির্ধারণ করা যায় — সিস্টেম স্বয়ংক্রিয়ভাবে প্রতি চক্রে একটি নতুন Draft Expense তৈরি করে অনুমোদনের জন্য অপেক্ষা করায় (স্বয়ংক্রিয়ভাবে পোস্ট করে না, যাতে ভুল এড়ানো যায়)।

## অন্যান্য মডিউলের সাথে সংযোগ

Expense সরাসরি Cash/Bank Balance কমায় (বা Payable বাড়ায়), General Ledger-এ প্রতিফলিত হয়, এবং Profit & Loss Statement-এ Operating Expense হিসেবে যোগ হয়।

---

# PHASE 12 — জেনারেল জার্নাল ও অ্যাকাউন্টিং ইঞ্জিন

**মডিউল:** ২০. General Journal

## ইউজার জার্নি

```
Create Journal → Add Debit Account → Add Credit Account
→ Enter Amount → Validate → Save Draft → Submit → Approve → Post
```

## বিস্তারিত ওয়ার্কফ্লো

এটি Accountant-এর ম্যানুয়াল অ্যাকাউন্টিং ইন্টারফেস — যখন কোনো অপারেশনাল মডিউল (Sales/Purchase/Expense) সরাসরি প্রযোজ্য নয়, তখন এখানে সরাসরি Journal তৈরি করা হয়।

1. `Accounting → Journal → + New Journal` — Journal Date, Reference Number, Description দেওয়ার পর একটি লাইন-টেবিল খোলে।
2. **প্রতিটি লাইনে:** Account (Account Selector দিয়ে সার্চ), Debit অথবা Credit কলামে Amount, লাইন-লেভেল Description। "+ Add Line" দিয়ে একাধিক Debit ও একাধিক Credit লাইন যোগ করা যায়।
3. টেবিলের নিচে সবসময় দুটি যোগফল দেখা যায় — **Total Debit** ও **Total Credit** — এবং একটি পার্থক্য নির্দেশক। যতক্ষণ দুটি না মেলে ততক্ষণ **Submit/Post বাটন disabled** থাকে এবং একটি লাল সতর্কবার্তা দেখায়: "Total Debit must equal Total Credit"।
4. দুটি মিললে **Save as Draft**, তারপর **Submit → Approve → Post** — একই স্ট্যান্ডার্ড অ্যাপ্রুভাল লাইফসাইকেল।
5. **Attachments:** প্রতিটি Journal-এ সাপোর্টিং ডকুমেন্ট (চুক্তি, প্রমাণ) আপলোড করা যায়।

### বিশেষ ধরনের Journal

- **Adjustment Journal:** সাধারণ ম্যানুয়াল সংশোধনী এন্ট্রি।
- **Accrual Journal:** পিরিয়ড-শেষে অনার্জিত আয়/ব্যয় বুক করার জন্য, প্রায়ই পরের পিরিয়ডে অটো-রিভার্সাল অপশনসহ ("Auto-reverse next period" চেকবক্স)।
- **Recurring Journal:** নির্দিষ্ট ফ্রিকোয়েন্সিতে অটো-জেনারেট হওয়া Draft (যেমন মাসিক ভাড়া)।
- **Reversal Journal:** কোনো Posted Journal-এর Details পেজে "Reverse" বাটন — একটি সম্পূর্ণ বিপরীত Journal তৈরি করে, মূল Journal-এর সাথে লিংক থাকে ("Reversed by JV-00123")।
- **Void:** ভুলভাবে তৈরি Journal, যদি এখনো ডাউনস্ট্রিম রিপোর্টে ব্যবহৃত না হয়ে থাকে, প্রশাসনিক অনুমতিতে Void করা যায়।

## এই মডিউল কীভাবে বাকি সিস্টেমের "মেরুদণ্ড"

```
Journal Entry → Journal Lines → General Ledger → Trial Balance → Financial Statements
```

Sales Invoice, Purchase Bill, Payment, Expense, Depreciation, Payroll — প্রতিটি মডিউল পর্দার আড়ালে এই **একই** Journal ইঞ্জিন ব্যবহার করে। ব্যবহারকারী শুধু Sales/Purchase-এর সহজ ফর্ম পূরণ করেন, কিন্তু সিস্টেম প্রতিটির পেছনে একই ধরনের Journal তৈরি করে — যা এই Phase 12-এর "General Journal" তালিকাতেও (source হিসেবে "Sales Invoice #INV-001" উল্লেখসহ) দেখা যায়।

---

# PHASE 13 — ফিক্সড অ্যাসেট

**মডিউল:** ২১. Fixed Assets

## ইউজার জার্নি

```
Asset Purchase → Asset Registration → Capitalization → Depreciation
→ Transfer → Revaluation → Disposal
```

## বিস্তারিত ওয়ার্কফ্লো

1. Asset ক্রয় সাধারণত একটি Purchase Bill-এর মাধ্যমে ঘটে (Product Type "Fixed Asset")। বিকল্পভাবে `Fixed Assets → + Register Asset` দিয়ে সরাসরি রেজিস্টার করা যায়।
2. **Asset Registration:** Asset Code, নাম, Category (ড্রপডাউন — প্রতিটির সাথে ডিফল্ট Depreciation Method ও Useful Life যুক্ত), Acquisition Date, Acquisition Cost, Location, Useful Life (মাসে), Depreciation Method (Straight Line/Declining Balance)।
3. **Capitalization:** Asset "Draft" অবস্থা থেকে "Capitalized/Active" করা হলে depreciation schedule জেনারেট হয় এবং Journal তৈরি হয়: `Fixed Asset Dr | Cash/Bank/Accounts Payable Cr`।
4. **Depreciation:** প্রতি মাস শেষে (Period Closing চেকলিস্টের অংশ হিসেবে, বা ম্যানুয়ালি `Fixed Assets → Run Depreciation`) সিস্টেম সব সক্রিয় Asset-এর জন্য একটি ব্যাচ Journal তৈরি করে: `Depreciation Expense Dr | Accumulated Depreciation Cr`। প্রতিটি Asset-এর Details পেজে "Depreciation Schedule" ট্যাবে ভবিষ্যতের সব মাসের প্রত্যাশিত এন্ট্রি দেখা যায়।
5. **Transfer:** এক শাখা/অবস্থান থেকে অন্যত্র — শুধু রেকর্ড আপডেট, কোনো অ্যাকাউন্টিং প্রভাব নেই।
6. **Revaluation:** বাজার মূল্য পরিবর্তনের জন্য — Journal: `Fixed Asset Dr/Cr | Revaluation Reserve Cr/Dr`।
7. **Disposal:** `Fixed Assets → Asset Details → Dispose` — Disposal Date, Sale Proceeds দিলে সিস্টেম Gain/Loss গণনা করে: Journal: `Cash Dr, Accumulated Depreciation Dr | Fixed Asset Cr, Gain on Disposal Cr` (অথবা Loss হলে বিপরীত)।

---

# PHASE 14 — পে-রোল

**মডিউল:** ২২. Payroll

## ইউজার জার্নি

```
Employee Setup → Salary Structure → Attendance/Leave
→ Payroll Processing → Review → Approval → Payslip
→ Salary Payment → Accounting Entry
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Payroll → Employees → + Add Employee` — ব্যক্তিগত তথ্য, Department, Designation, যোগদানের তারিখ।
2. **Salary Structure:** Basic, Allowances, Deductions নির্ধারণ করা হয় (প্রতিটি Employee-তে বা টেমপ্লেট হিসেবে)।
3. **Attendance/Leave:** মাসজুড়ে HR উপস্থিতি/ছুটি রেকর্ড করে (ম্যানুয়াল এন্ট্রি বা বায়োমেট্রিক ইমপোর্ট)।
4. মাস শেষে `Payroll → + Process Payroll` — Period নির্বাচন করলে সিস্টেম সব Employee-র জন্য Gross Pay, Deductions, Net Pay স্বয়ংক্রিয়ভাবে গণনা করে একটি Draft Payroll Run তৈরি করে।
5. **Review:** HR/Payroll Manager প্রতিটি Employee-র লাইন যাচাই করে প্রয়োজনে ম্যানুয়াল সমন্বয় করেন।
6. **Approval:** Company Admin/Finance Head অনুমোদন করেন।
7. **Payslip:** অনুমোদনের পর প্রতিটি Employee-র জন্য একটি Payslip জেনারেট হয় (PDF, ডাউনলোডযোগ্য/ইমেইলযোগ্য)।
8. **Post (Accrual):** Journal: `Salary Expense Dr | Salary Payable Cr`।
9. **Salary Payment:** `Payroll → Pay Salaries` — Cash/Bank থেকে প্রকৃত পেমেন্ট করলে Journal: `Salary Payable Dr | Cash/Bank Cr`।

---

# PHASE 15 — বাজেট

**মডিউল:** ২৩. Budget

## ইউজার জার্নি

```
Create Budget → Select Fiscal Year → Select Account
→ Enter Budget → Monthly Allocation → Approve
→ Monitor → Budget vs Actual
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Budget → + Create Budget` — Fiscal Year, ঐচ্ছিকভাবে Branch/Department নির্বাচন করা হয়।
2. একটি গ্রিড-স্টাইল ফর্মে প্রতিটি Account (সাধারণত Income/Expense অ্যাকাউন্ট) সারিতে এবং প্রতিটি মাস কলামে থাকে — ব্যবহারকারী প্রতিটি ঘরে মাসিক বাজেট পরিমাণ লিখেন, অথবা একটি বার্ষিক অঙ্ক দিয়ে "Distribute Evenly" ক্লিক করে ১২ মাসে সমানভাবে ভাগ করে নেন।
3. **Approve** হলে Budget লক হয়ে যায় (পরিবর্তনের জন্য নতুন Revision দরকার)।
4. `Budget → Budget vs Actual` রিপোর্টে প্রতিটি Account-এর Budgeted, Actual (Ledger থেকে সরাসরি টানা), এবং Variance (Amount ও %) পাশাপাশি দেখা যায় — নেগেটিভ Variance লাল রঙে হাইলাইট হয়।
5. Account, Department, Branch, Month, Year দিয়ে ফিল্টার করা যায়।

## অ্যাকাউন্টিং ইমপ্যাক্ট

Budget নিজে কোনো Journal তৈরি করে না — এটি সম্পূর্ণ একটি Planning/Monitoring স্তর যা Ledger-এর প্রকৃত Actual ডেটার সাথে তুলনা করে।

---

# PHASE 16 — অ্যাপ্রুভাল ওয়ার্কফ্লো

**মডিউল:** ২৭. Approval Workflow

## জীবনচক্র (সব মডিউলে অভিন্ন)

```
Draft → Submitted → Pending Approval → Approved → Posted
Pending Approval → Rejected → Draft (সংশোধন করে পুনরায় Submit)
```

## বিস্তারিত ওয়ার্কফ্লো

1. Administrator `Administration → Approval Workflow → + Create Rule` এ গিয়ে নির্ধারণ করেন: কোন Module (Sales/Purchase/Payment/Receipt/Expense/Journal/Payroll/Budget), কোন Amount Range-এ, কোন Role/User অনুমোদন দেবেন, এবং একাধিক ধাপ হলে তার Sequence।
2. যখন কোনো ব্যবহারকারী একটি লেনদেন Submit করেন, সিস্টেম প্রথমে ম্যাচ করা Approval Rule খুঁজে বের করে এবং প্রাসঙ্গিক Approver-দের কাছে একটি **Notification** ও তাদের **"My Approvals"** ইনবক্সে একটি আইটেম পাঠায়।
3. Approver `My Approvals` এ গিয়ে আইটেমের বিস্তারিত দেখেন এবং **Approve** বা **Reject** (মন্তব্যসহ) করেন।
4. Reject হলে লেনদেন "Draft"-এ ফিরে যায়, মূল ব্যবহারকারী Notification পান এবং Reject-এর কারণ দেখতে পান, সংশোধন করে পুনরায় Submit করতে পারেন।
5. সব ধাপ Approve হলে লেনদেন "Approved" হয় এবং (কনফিগারেশন অনুযায়ী) হয় স্বয়ংক্রিয়ভাবে Post হয়, অথবা মূল ব্যবহারকারীকে ম্যানুয়ালি Post করতে হয়।

## বিজনেস রুল

- একজন ব্যবহারকারী নিজের তৈরি করা লেনদেন নিজে অনুমোদন করতে পারেন না (Segregation of Duties)।
- Amount-ভিত্তিক Approval Rule একাধিক স্তরে হতে পারে (যেমন ৳৫০,০০০ পর্যন্ত ম্যানেজার, তার বেশি হলে Finance Head)।

---

# PHASE 17 — রিপোর্টিং

**মডিউল:** ২৪. Accounting Reports

## ইউজার জার্নি (সাধারণ প্যাটার্ন সব রিপোর্টের জন্য)

```
Report Menu → Select Report → Apply Filters → Generate
→ Review → Drill Down → Export / Print
```

## বিস্তারিত ওয়ার্কফ্লো

1. `Reports` মেনুতে ক্যাটাগরি অনুযায়ী সাজানো তালিকা: Accounting, Sales, Purchase, Inventory, AR, AP, Financial Statements, Tax, Fixed Assets, Payroll, Budget।
2. প্রতিটি রিপোর্ট খোলার পর উপরে একটি Filter Bar থাকে: Date Range, Branch, Account/Customer/Supplier/Warehouse (রিপোর্ট অনুযায়ী প্রাসঙ্গিক ফিল্টার), এবং **Generate** বাটন।
3. রিপোর্ট জেনারেট হওয়ার পর একটি টেবিল/সামারি দেখা যায়, উপরে **Export PDF**, **Export Excel**, **Print** বাটন থাকে।
4. **Drill-down উদাহরণ:**

```
Trial Balance → Account-এ ক্লিক → Account Ledger
→ নির্দিষ্ট লেনদেনে ক্লিক → Transaction Details → Journal Entry
```

   অর্থাৎ, Trial Balance-এ কোনো অ্যাকাউন্টের ব্যালেন্সে ক্লিক করলে সেই অ্যাকাউন্টের সব এন্ট্রি (Ledger) দেখা যায়; সেখানে কোনো একটি লাইনে ক্লিক করলে মূল লেনদেন (Invoice/Bill/Journal) এর ডিটেইলস পেজে চলে যাওয়া যায় — যাতে ব্যবহারকারী সবসময় "এই সংখ্যাটি কোথা থেকে এলো" প্রশ্নের উত্তর এক ক্লিকেই পান।

## রিপোর্ট তালিকা (সংক্ষিপ্ত)

General Ledger, Account Ledger, Journal Report, Trial Balance, Customer/Supplier Statement, AR/AP Aging, Sales/Purchase Summary ও ব্রেকডাউন রিপোর্ট, Inventory (Stock Summary/Movement/Valuation/Low Stock/Expiry), Cash/Bank Reports, Expense Reports, Tax Reports, Fixed Asset Reports, Payroll Reports, Budget vs Actual — প্রতিটি একই Filter → Generate → Drill-down → Export প্যাটার্ন অনুসরণ করে।

---

# PHASE 18 — ফাইন্যান্সিয়াল স্টেটমেন্ট

**মডিউল:** ২৫. Financial Statements

## বিস্তারিত ওয়ার্কফ্লো

1. `Reports → Financial Statements` এ চারটি প্রধান রিপোর্ট: Profit & Loss, Balance Sheet, Cash Flow Statement, Statement of Changes in Equity।
2. Period/Date Range নির্বাচন করে **Generate** করলে সিস্টেম **সরাসরি Ledger থেকে** রিয়েল-টাইমে হিসাব করে রিপোর্ট তৈরি করে — কোনো মান ম্যানুয়ালি সংরক্ষিত থাকে না।

```
Sales → Revenue Account → General Ledger → Trial Balance → Profit & Loss
Assets, Liabilities, Equity → Balance Sheet (Assets = Liabilities + Equity)
```

3. **Profit & Loss:** Revenue − COGS = Gross Profit; − Operating Expenses = Operating Profit; +/− Other Income/Expense = Net Profit। প্রতিটি লাইন-আইটেমে ক্লিক করলে সংশ্লিষ্ট Account Ledger-এ ড্রিল-ডাউন হয়।
4. **Balance Sheet:** Current Assets + Fixed Assets = মোট Assets; Current + Long-term Liabilities = মোট Liabilities; Capital + Retained Earnings = Equity। সিস্টেম সবসময় যাচাই করে Assets = Liabilities + Equity — অমিল হলে (তাত্ত্বিকভাবে হওয়া উচিত নয়, কিন্তু ডেটা সমস্যা থাকলে) একটি সতর্কবার্তা দেখানো হয়।
5. **Cash Flow Statement:** Operating/Investing/Financing Activities — Indirect Method (Net Profit থেকে শুরু করে Non-cash আইটেম সমন্বয়) ব্যবহার করে Ledger থেকে গণনা করা হয়।
6. **Statement of Changes in Equity:** Opening Equity + Net Profit − Dividends/Drawings = Closing Equity।

## গুরুত্বপূর্ণ নীতি

এই সব রিপোর্ট **কোনো ম্যানুয়ালি এন্ট্রি করা সংখ্যা নয়** — এগুলো সবসময় জমা হওয়া Journal Entry থেকে লাইভ গণনা করা হয়। এই কারণেই General Journal (Phase 12) এবং Chart of Accounts (Phase 2)-এর সঠিকতা এত গুরুত্বপূর্ণ।

---

# PHASE 19 — ড্যাশবোর্ড

**মডিউল:** ৩০. Dashboard

## বিস্তারিত ওয়ার্কফ্লো

লগইন করার পর ব্যবহারকারী সরাসরি Dashboard-এ আসেন (রোল অনুযায়ী কাস্টমাইজড — নিচে Phase 34 দেখুন)। উপরে ফিল্টার বার: Company, Branch, Date Range, Fiscal Year।

**কার্ড/মেট্রিক্স:** Total Revenue, Total Expense, Net Profit, Accounts Receivable, Accounts Payable, Cash Balance, Bank Balance, Inventory Value, Overdue Receivable, Overdue Payable — প্রতিটি কার্ডে একটি সংক্ষিপ্ত ট্রেন্ড আইকন (↑/↓) থাকে।

**চার্ট:** Revenue Trend, Expense Trend, Profit Trend, Sales Trend, Purchase Trend, Cash Flow, Receivable Trend, Payable Trend — লাইন/বার চার্ট আকারে।

**ড্রিল-ডাউন:** যেকোনো কার্ড বা চার্টে ক্লিক করলে সংশ্লিষ্ট বিস্তারিত রিপোর্টে (যেমন "Accounts Receivable" কার্ডে ক্লিক করলে AR Aging Report-এ) সরাসরি নিয়ে যায়।

Dashboard-এর সব ডেটা ব্যবহারকারীর কোম্পানি/শাখা/মডিউল পারমিশন অনুযায়ী ফিল্টার হয় — একজন Sales Executive Payroll-সংক্রান্ত কোনো কার্ড দেখতে পাবেন না।

---

# PHASE 20 — নোটিফিকেশন

**মডিউল:** ২৮. Notifications

## প্যাটার্ন

```
Event → Notification → User → Action
```

উদাহরণ: `Invoice Due Date আসন্ন (৩ দিন বাকি) → System Event তৈরি হয় → সংশ্লিষ্ট Sales Executive/Accountant-এর কাছে In-app + Email Notification যায় → ব্যবহারকারী ক্লিক করলে সরাসরি Invoice Details-এ যান → Payment Reminder পাঠান বা নিজে ফলো-আপ করেন।`

কভার করা ইভেন্ট: Pending Approval, Invoice Due/Overdue, Payment Due, Low Stock, Purchase Approval, Expense Approval, Journal Approval, Payroll Completion, Period Closing।

`Administration → Notification Preferences` এ প্রতিটি ইউজার নিজের জন্য কোন ইভেন্টে In-app/Email/উভয় চান তা কনফিগার করতে পারেন।

---

# PHASE 21 — ডকুমেন্ট / অ্যাটাচমেন্ট

**মডিউল:** ২৯. Documents / Attachments

## প্যাটার্ন

```
Invoice → Attach PDF → Save → View Attachment → Download
```

Sales Invoice, Purchase Bill, Payment, Receipt, Expense, Journal, Bank Reconciliation, Fixed Assets, Payroll — প্রতিটি লেনদেনের Details পেজে একটি **"Attachments"** ট্যাব/সেকশন থাকে, যেখানে ড্র্যাগ-ড্রপ আপলোড, থাম্বনেইল প্রিভিউ (ছবি/PDF), ফাইলের নাম-সাইজ-আপলোডকারী, এবং Download/Delete বাটন থাকে। অ্যাটাচমেন্ট দেখার পারমিশন মূল রেকর্ডের পারমিশনের সাথে সংযুক্ত — অর্থাৎ যিনি Invoice দেখতে পারেন, শুধু তিনিই তার Attachment দেখতে পারবেন।

---

# PHASE 22 — অডিট ট্রেইল

**মডিউল:** ২৬. Audit Trail

## প্যাটার্ন

```
User (with permission) → Audit Trail → Select Module
→ Select Record → View Activity
```

`Administration → Audit Trail` এ ব্যবহারকারী Module (Sales/Journal/Customer ইত্যাদি) ও নির্দিষ্ট Record নির্বাচন করে একটি টাইমলাইন দেখেন — Created, Updated, Approved, Rejected, Posted, Voided, Reversed — প্রতিটি এন্ট্রিতে **Who** (কে করেছেন), **What** (কী পরিবর্তন), **When** (কখন), **Old Value → New Value** পাশাপাশি দেখানো হয়।

**কেন Posted লেনদেন মুছে ফেলা হয় না:** একবার Post হওয়া অ্যাকাউন্টিং লেনদেন ডিলিট করলে ঐতিহাসিক রিপোর্ট, ইতিমধ্যে জেনারেট করা Financial Statement, এবং Audit-যোগ্যতা নষ্ট হয়ে যায়। তাই সিস্টেম সবসময় **Void/Reverse/Cancel** ব্যবহার করে — মূল রেকর্ড দৃশ্যমান থাকে (স্ট্যাটাস "Voided/Reversed" সহ), এবং একটি নতুন সংশোধনী এন্ট্রি তৈরি হয়। এতে যেকোনো সময় "কে, কখন, কেন এই সংখ্যাটি বদলালো" প্রশ্নের সম্পূর্ণ ইতিহাস অক্ষত থাকে।

---

# PHASE 23 — পিরিয়ড ক্লোজিং

**মডিউল:** ৩১. Period Closing

## ইউজার জার্নি (মাস-শেষের চেকলিস্ট)

```
Review Transactions → Complete Sales → Complete Purchases
→ Reconcile Cash → Reconcile Bank → Review AR → Review AP
→ Review Inventory → Post Expenses → Post Depreciation
→ Post Accruals → Review Tax/VAT → Generate Trial Balance
→ Review Financial Statements → Close Period
```

## বিস্তারিত ওয়ার্কফ্লো

1. মাস শেষে Accountant `Accounting → Period Closing → [মাস নির্বাচন]` এ যান, যেখানে একটি **ইন্টারঅ্যাক্টিভ চেকলিস্ট** দেখা যায়:
   - সব Draft/Pending Sales ও Purchase লেনদেন সম্পন্ন/অনুমোদিত হয়েছে কি না (না হলে সরাসরি লিংক দিয়ে সমাধান করা যায়)।
   - Cash ও Bank Reconciliation সম্পন্ন হয়েছে কি না।
   - AR/AP Aging পর্যালোচনা করা হয়েছে কি না।
   - Inventory Count/Adjustment সম্পন্ন হয়েছে কি না।
   - সব Expense পোস্ট হয়েছে কি না।
   - Depreciation Run সম্পন্ন হয়েছে কি না (এক ক্লিকে "Run Depreciation for this Period" বাটন এখানেই থাকে)।
   - প্রয়োজনীয় Accrual Journal পোস্ট হয়েছে কি না।
   - Tax/VAT রিপোর্ট পর্যালোচিত হয়েছে কি না।
2. প্রতিটি ধাপ সম্পন্ন হলে সবুজ চেকমার্ক দেখায়। সব ধাপ সম্পন্ন হলে **"Generate Trial Balance"** ও **"Review Financial Statements"** বাটন সক্রিয় হয়।
3. সব পর্যালোচনা শেষে **"Close Period"** বাটনে ক্লিক করলে সিস্টেম নিশ্চিতকরণ চায় ("এই পিরিয়ড বন্ধ করলে সাধারণ ব্যবহারকারীরা এতে নতুন লেনদেন যোগ করতে পারবেন না — আপনি কি নিশ্চিত?")।
4. নিশ্চিত করলে Period-এর স্ট্যাটাস "Closed" হয়ে যায়।

## বন্ধ হওয়ার পর যা নিষিদ্ধ হয়

- সাধারণ ব্যবহারকারী এই Period-এর তারিখে নতুন Journal/Invoice/Bill পোস্ট করতে পারবেন না — Journal Entry ফর্মে সেই তারিখ বাছাই করলে সাথে সাথে এরর দেখাবে।
- Posted লেনদেন এডিট/ডিলিট করা যাবে না।

## পুনরায় খোলা (Reopen)

শুধুমাত্র উপযুক্ত পারমিশনধারী ব্যবহারকারী (যেমন Company Admin) `Period Closing → [Closed Period] → Reopen` করতে পারেন — একটি কারণ লেখা বাধ্যতামূলক, এবং এই কাজটি Audit Trail-এ বিশেষভাবে লগ হয়।

---

# PHASE 24 — ইয়ার এন্ড ক্লোজিং

**মডিউল:** ৩২. Year End Closing

## ইউজার জার্নি

```
Complete Transactions → Adjustments → Depreciation → Accruals
→ Tax Adjustments → Trial Balance → Financial Statements
→ Calculate Net Profit/Loss → Closing Entries → Retained Earnings
→ Close Financial Year → Create New Financial Year
→ Carry Forward Opening Balances
```

## বিস্তারিত ওয়ার্কফ্লো

1. বছরের সব ১২টি Period "Closed" হওয়ার পর `Accounting → Year End Closing → [Fiscal Year]` এ প্রবেশ করা যায়।
2. সিস্টেম চূড়ান্ত Trial Balance ও Financial Statements দেখায় পর্যালোচনার জন্য।
3. **"Calculate Net Profit/Loss"** ক্লিক করলে সিস্টেম সব Revenue ও Expense অ্যাকাউন্টের বার্ষিক ব্যালেন্স গণনা করে Net Profit/Loss বের করে এবং একটি প্রিভিউ দেখায়।
4. **"Generate Closing Entries"** ক্লিক করলে সিস্টেম স্বয়ংক্রিয়ভাবে Closing Journal তৈরি করে: সব Revenue ও Expense অ্যাকাউন্ট শূন্যে নামিয়ে Net Profit/Loss **Retained Earnings**-এ স্থানান্তর করে।
5. **"Close Financial Year"** ক্লিক করলে বছরটির স্ট্যাটাস "Closed" হয়ে যায়।
6. সিস্টেম স্বয়ংক্রিয়ভাবে **"Create New Financial Year"** এর একটি প্রম্পট দেখায় — নতুন বছরের শুরু/শেষ তারিখ কনফার্ম করলে নতুন Fiscal Year ও তার ১২টি Period তৈরি হয়।
7. **"Carry Forward Opening Balances"** — সব Balance Sheet অ্যাকাউন্ট (Asset/Liability/Equity, যার মধ্যে হালনাগাদ Retained Earnings-ও রয়েছে) নতুন বছরের Opening Balance হিসেবে স্বয়ংক্রিয়ভাবে বহন করা হয়; Revenue/Expense অ্যাকাউন্ট শূন্য থেকে শুরু হয়।

## যা ঘটে

- **Revenue/Expense:** শূন্যে রিসেট হয়ে নতুন বছরে নতুনভাবে জমা হওয়া শুরু হয়।
- **Assets/Liabilities/Equity:** অপরিবর্তিত মূল্যে নতুন বছরে বহন হয়।
- **Retained Earnings:** এই বছরের Net Profit/Loss দিয়ে আপডেট হয়ে নতুন বছরে বহন হয়।
- **Opening Balances:** নতুন Fiscal Year-এর Phase 4-এর মতো একটি Opening Balance Journal স্বয়ংক্রিয়ভাবে তৈরি হয়, যা আগের বছরের সমাপনী ব্যালেন্স থেকে সরাসরি আসে (ম্যানুয়াল পুনঃএন্ট্রির প্রয়োজন নেই)।

---

# END-TO-END BUSINESS USER JOURNEYS

## Journey 1 — নতুন কোম্পানি সেটআপ

```
Login → Company → Branch → Users → Accounting Configuration
→ Fiscal Year → Currency → Tax → Chart of Accounts
→ Opening Balance → Dashboard
```

নতুন গ্রাহক লগইন করে কোম্পানি তৈরি করেন, শাখা ও ব্যবহারকারী যোগ করেন, তারপর অ্যাকাউন্টিং ফাউন্ডেশন (Fiscal Year, Currency, Tax, CoA) সাজান, Opening Balance পোস্ট করেন, এবং শেষে একটি খালি কিন্তু সম্পূর্ণ কনফিগার করা Dashboard দেখেন — লেনদেন শুরু করার জন্য প্রস্তুত।

## Journey 2 — নগদ বিক্রয় (Cash Sale)

```
Customer → Product → Sales Invoice → Payment → Receipt
→ Journal → Ledger → Dashboard → P&L
```

গ্রাহক নির্বাচন → পণ্য যোগ → Invoice তৈরি ও পোস্ট (`AR Dr | Sales Cr`) → সাথে সাথে Payment রেকর্ড (`Cash Dr | AR Cr`) → নিট প্রভাব: Cash বৃদ্ধি, Revenue বৃদ্ধি, AR সাময়িকভাবে বেড়ে আবার শূন্যে ফিরে আসে → Dashboard-এ Revenue কার্ড ও P&L-এ তাৎক্ষণিক প্রতিফলন।

## Journey 3 — বাকিতে বিক্রয় (Credit Sale)

```
Customer → Sales Invoice → Accounts Receivable → Customer Ledger
→ Due Date → Payment → Allocation → AR Updated → Financial Reports
```

Invoice পোস্ট হওয়ার পর AR বৃদ্ধি পায় ও Due Date পর্যন্ত বকেয়া থাকে; পরবর্তীতে গ্রাহক আংশিক/পূর্ণ পেমেন্ট করলে তা Invoice-এ Allocate হয়, AR কমে, এবং সব রিপোর্টে (Aging, Statement, Balance Sheet) আপডেট প্রতিফলিত হয়।

## Journey 4 — বাকিতে ক্রয় (Purchase on Credit)

```
Supplier → Purchase Order → Goods Receive → Purchase Bill
→ Accounts Payable → Supplier Ledger → Payment → AP Updated
```

## Journey 5 — ইনভেন্টরি বিক্রয়

```
Product → Sales → Delivery → Inventory Reduction → COGS
→ Sales Revenue → Receivable → Journal → Ledger
```

Delivery-তে স্টক কমে, Invoice Post-এ `COGS Dr | Inventory Cr` ও `AR Dr | Sales Cr` একসাথে একটি Journal-এ পোস্ট হয়, ফলে Gross Profit তাৎক্ষণিকভাবে P&L-এ সঠিকভাবে প্রতিফলিত হয়।

## Journey 6 — এক্সপেন্স পেমেন্ট

```
Expense → Expense Account → Cash/Bank → Journal → Ledger → P&L
```

## Journey 7 — ব্যাংক রিকনসিলিয়েশন

```
Bank → Statement → Import → Match → Review → Reconcile → Bank Balance
```

## Journey 8 — মাস-শেষ ক্লোজিং

```
Transactions → Reconciliation → Adjustments → Depreciation
→ Trial Balance → Financial Statements → Close Period
```

## Journey 9 — বছর-শেষ ক্লোজিং

```
Period Completion → Adjustments → Financial Statements
→ Closing Entries → Retained Earnings → New Fiscal Year → Opening Balances
```

---

# GLOBAL USER NAVIGATION (গ্লোবাল নেভিগেশন কাঠামো)

```
Dashboard

Accounting
├── Chart of Accounts
├── Journal
├── General Ledger
├── Trial Balance
└── Period Closing

Sales
├── Quotations
├── Orders
├── Deliveries
├── Invoices
├── Credit Notes
└── Receipts

Purchases
├── Requisitions
├── Orders
├── Goods Receive
├── Bills
├── Debit Notes
└── Payments

Receivables
├── Customers
├── Outstanding
├── Aging
└── Statements

Payables
├── Suppliers
├── Outstanding
├── Aging
└── Statements

Inventory
├── Products
├── Warehouses
├── Stock
├── Transfers
├── Adjustments
└── Valuation

Cash & Bank
├── Cash
├── Bank Accounts
├── Transactions
└── Reconciliation

Expenses
├── Expenses
└── Categories

Fixed Assets
├── Assets
├── Depreciation
└── Disposal

Payroll
├── Employees
├── Salary
├── Payroll
└── Payslips

Budget
├── Budgets
└── Budget vs Actual

Reports
├── Accounting
├── Sales
├── Purchase
├── Inventory
├── AR
├── AP
└── Financial Statements

Administration
├── Company
├── Branches
├── Users
├── Roles & Permissions
├── Approval Workflow
├── Audit Trail
├── Notifications
└── Settings
```

---

# SCREEN-LEVEL WORKFLOW (নমুনা: Customer মডিউল)

## Customer List

নেভিগেশন: `Sales → Customers → Customer List`

উপলব্ধ অ্যাকশন: Search (নাম/কোড দিয়ে), Filter (Active/Inactive/Overdue Balance), + Create Customer, View, Edit, Deactivate।

## Create Customer

বাটন: `+ Create Customer`

ফর্মে থাকে: Customer Name, Code, Phone, Email, Address, Tax/VAT, Credit Limit, Payment Terms, Opening Balance।

সাবমিশনের পরের ফ্লো:

```
Create Customer → Validation → Save → Success Message → Customer Details
```

## Customer Details

ট্যাব: Overview, Transactions, Ledger, Statement, Invoices, Payments।

- **Overview:** মূল তথ্য, বর্তমান বকেয়া ব্যালেন্স, Credit Limit ব্যবহারের অগ্রগতি বার।
- **Transactions:** সব ধরনের লেনদেন (Invoice/Credit Note/Receipt) একটি একক টাইমলাইনে।
- **Ledger:** ডেবিট-ক্রেডিট এন্ট্রি ও রানিং ব্যালেন্স, প্রতিটি এন্ট্রি ক্লিকযোগ্য (মূল লেনদেনে ড্রিল-ডাউন)।
- **Statement:** তারিখ-রেঞ্জ নির্বাচন করে প্রিন্ট/PDF-যোগ্য স্টেটমেন্ট জেনারেট।
- **Invoices/Payments:** এই গ্রাহক সম্পর্কিত শুধু Invoice/Payment তালিকা, স্ট্যাটাস ফিল্টারসহ।

> এই একই স্তরের বিস্তারিততা প্রতিটি প্রধান মডিউলে (Supplier, Product, Sales Invoice, Purchase Bill, Journal, Fixed Asset, Employee ইত্যাদি) প্রযোজ্য — প্রতিটির নিজস্ব List → Create → Details (ট্যাবসহ) কাঠামো।

---

# STATUS & LIFECYCLE DOCUMENTATION (স্ট্যাটাস ও লাইফসাইকেল)

সব লেনদেন-ভিত্তিক মডিউল (Sales Invoice, Purchase Bill, Journal, Expense, Payroll Run, Budget) একই মৌলিক স্ট্যাটাস-মেশিন অনুসরণ করে:

```
Draft → Submitted → Approved → Posted
Draft → Cancelled
Posted → Void
Posted → Reverse → Corrected Transaction (নতুন সংশোধনী এন্ট্রি)
Pending Approval → Rejected → Draft
```

| স্ট্যাটাস | অনুমোদিত অ্যাকশন |
|---|---|
| Draft | Edit, Delete, Submit, Cancel |
| Submitted / Pending Approval | View, Approve, Reject (শুধু Approver-এর জন্য) |
| Approved | Post |
| Posted | View, Void, Reverse, Attach Document, Print |
| Rejected | Edit (ফিরে Draft-এ), Delete |
| Voided / Reversed | শুধু View (আর কোনো অ্যাকশন নেই — ইতিহাস হিসেবে সংরক্ষিত) |

---

# অ্যাকাউন্টিং ইমপ্যাক্ট সারাংশ (উদাহরণসহ)

### Sales Invoice

ব্যবসায়িক ঘটনা: গ্রাহক ৳১,০০,০০০ টাকার পণ্য ক্রয় করলেন।

```
Accounts Receivable   Dr  ১,০০,০০০
    Sales Revenue          Cr  ১,০০,০০০
```

ইনভেন্টরি জড়িত থাকলে:

```
COGS                   Dr  ৬০,০০০
    Inventory              Cr  ৬০,০০০
```

**ফলাফল:** গ্রাহকের বকেয়া বৃদ্ধি পায়। ইনভেন্টরি হ্রাস পায়। রেভিনিউ বৃদ্ধি পায়। COGS বৃদ্ধি পায়। নিট মুনাফা প্রভাবিত হয় (এই ক্ষেত্রে ৪০,০০০ টাকা Gross Profit যোগ হয়)।

### Customer Receipt

```
Cash/Bank              Dr  ১,০০,০০০
    Accounts Receivable    Cr  ১,০০,০০০
```

**ফলাফল:** নগদ/ব্যাংক বৃদ্ধি পায়, গ্রাহকের বকেয়া কমে যায়। মোট Asset অপরিবর্তিত থাকে (এক Asset থেকে অন্য Asset-এ রূপান্তর)।

### Purchase Bill

```
Inventory/Expense       Dr  ৫০,০০০
Input Tax                Dr   ৫,০০০
    Accounts Payable         Cr  ৫৫,০০০
```

### Supplier Payment

```
Accounts Payable        Dr  ৫৫,০০০
    Cash/Bank                Cr  ৫৫,০০০
```

### Expense

```
Expense Account          Dr   ৫,০০০
    Cash/Bank                 Cr   ৫,০০০
```

**ফলাফল:** নগদ/ব্যাংক কমে, খরচ বৃদ্ধি পায়, নিট মুনাফা হ্রাস পায়।

### Depreciation

```
Depreciation Expense     Dr    ৮৩৩.৩৩
    Accumulated Depreciation  Cr    ৮৩৩.৩৩
```

**ফলাফল:** Asset-এর বই মূল্য (Book Value) হ্রাস পায়, খরচ বৃদ্ধি পায়, নিট মুনাফা হ্রাস পায়।

### Payroll

```
Salary Expense           Dr   ৫০,০০০
    Salary Payable            Cr   ৫০,০০০
...পেমেন্টের সময়:
Salary Payable           Dr   ৫০,০০০
    Bank                       Cr   ৫০,০০০
```

---

# ERROR & EXCEPTION FLOWS (এরর ও এক্সেপশন ফ্লো)

প্রতিটি ক্ষেত্রেই প্যাটার্ন অভিন্ন:

```
User Action → System Validation → Error Message → User Correction → Retry
```

| পরিস্থিতি | সিস্টেমের আচরণ |
|---|---|
| অবৈধ তথ্য এন্ট্রি | ফর্ম ফিল্ডের নিচে লাল রঙে নির্দিষ্ট এরর মেসেজ দেখায়; Submit বাটন disabled থাকে যতক্ষণ না সংশোধন হয় |
| Debit ≠ Credit | Journal/Invoice ফর্মের নিচে পার্থক্যের পরিমাণ লাল রঙে দেখানো হয়, Post/Submit disabled |
| Period Closed | "এই তারিখের পিরিয়ড বন্ধ আছে, লেনদেন পোস্ট করা যাবে না" — তারিখ পরিবর্তনের সাজেশনসহ |
| নিষ্ক্রিয় (Inactive) অ্যাকাউন্ট | Account Selector-এ সেই অ্যাকাউন্ট দেখানো হয় না; সরাসরি ID দিয়ে চেষ্টা করলে "এই অ্যাকাউন্ট নিষ্ক্রিয়" এরর |
| গ্রাহকের Credit Limit অতিক্রম | Invoice Submit করার সময় সতর্কবার্তা: "Credit Limit অতিক্রম হয়েছে" — নির্দিষ্ট পারমিশনধারী ব্যবহারকারী "Override & Continue" করতে পারেন, অন্যরা পারেন না |
| অপর্যাপ্ত স্টক | Delivery/Sales Invoice-এ পরিমাণ প্রাপ্ত স্টকের বেশি হলে এরর, ব্যাকঅর্ডার/আংশিক ডেলিভারির সাজেশন |
| ডুপ্লিকেট ইনভয়েস নম্বর | "এই নম্বর ইতিমধ্যে ব্যবহৃত হয়েছে" — পরবর্তী উপলব্ধ নম্বর সাজেস্ট করা হয় |
| পেমেন্ট বকেয়ার চেয়ে বেশি | "পরিমাণ বকেয়ার চেয়ে বেশি" — Advance/Overpayment হিসেবে সংরক্ষণের অপশন দেখানো হয় |
| অননুমোদিত ইউজারের Approve করার চেষ্টা | "আপনার এই লেনদেন অনুমোদনের অনুমতি নেই" — বাটনই দেখানো হয় না (permission-aware UI) |
| Posted লেনদেন এডিটের চেষ্টা | Edit বাটন থাকে না; শুধু Void/Reverse অপশন দেখায় |
| ভুল Tax কনফিগারেশন | "এই Product-এর জন্য কোনো বৈধ Tax Rate পাওয়া যায়নি" — Tax Settings-এ যাওয়ার শর্টকাট লিংক |
| এক্সচেঞ্জ রেট অনুপস্থিত | "এই তারিখের জন্য কোনো Exchange Rate পাওয়া যায়নি" — সরাসরি Exchange Rate এন্ট্রি ফর্ম খোলার অপশন |

---

# USER EXPERIENCE PRINCIPLES (ইউজার এক্সপেরিয়েন্স নীতিমালা)

পুরো সিস্টেমে নিচের UX নীতিগুলো সামঞ্জস্যপূর্ণভাবে প্রয়োগ করা হয়:

- **Breadcrumb:** সবসময় বর্তমান অবস্থান দেখায় (যেমন `Sales > Invoices > INV-0012`)।
- **Search ও Filter:** প্রতিটি তালিকা পেজে দ্রুত সার্চ বার ও প্রাসঙ্গিক ফিল্টার।
- **Pagination:** বড় তালিকায় পেজ-ভিত্তিক লোডিং, লোড হওয়া সারির সংখ্যা দেখানো হয়।
- **Empty State:** কোনো ডেটা না থাকলে সহায়ক বার্তা ও একটি "+ Create" শর্টকাট বাটন।
- **Loading State:** ডেটা লোড হওয়ার সময় স্কেলিটন/স্পিনার — কখনো ফাঁকা সাদা স্ক্রিন নয়।
- **Validation Message:** ফিল্ড-লেভেলে তাৎক্ষণিক, স্পষ্ট ভাষায়।
- **Confirmation Dialog:** যেকোনো অপরিবর্তনীয় অ্যাকশনের (Void, Delete, Close Period, Reverse) আগে নিশ্চিতকরণ ডায়ালগ।
- **Success/Error Message:** টোস্ট নোটিফিকেশন আকারে, সংক্ষিপ্ত ও কার্যকর ভাষায়।
- **Unsaved Changes Warning:** ফর্মে অসংরক্ষিত পরিবর্তন রেখে পেজ ত্যাগ করার চেষ্টা করলে সতর্কবার্তা।
- **Permission-aware Actions:** যে অ্যাকশনের অনুমতি নেই, তা UI-তে দেখানোই হয় না (disabled করে রাখা নয়, লুকিয়ে রাখা — বিভ্রান্তি এড়াতে)।
- **Status Badge:** প্রতিটি লেনদেনের স্ট্যাটাস রঙিন ব্যাজ আকারে (Draft = ধূসর, Pending = হলুদ, Posted = সবুজ, Overdue = লাল, Voided = কালো/স্ট্রাইকথ্রু)।
- **Drill-down Navigation:** যেকোনো সংখ্যা/রিপোর্ট লাইন থেকে মূল উৎস লেনদেন পর্যন্ত ক্লিক-করে-করে যাওয়া যায়।
- **Back Navigation:** সামঞ্জস্যপূর্ণ Back বাটন/ব্রাউজার-ব্যাক সাপোর্ট, ফর্মের অবস্থা হারায় না।
- **সামঞ্জস্যপূর্ণ ফর্ম ও লেনদেন প্যাটার্ন:** প্রতিটি মডিউলের List → Create → Details → Ledger/History কাঠামো একই রকম, যাতে একটি মডিউল শিখলে অন্যগুলো সহজেই বোঝা যায়।

ব্যবহারকারীর সবসময় এই চারটি প্রশ্নের উত্তর স্পষ্ট থাকা উচিত: **আমি কোথায় আছি? আমি কী করছি? কী ঘটলো? এরপর আমি কী করতে পারি?**

---

# COMPLETE ACCOUNTING SYSTEM FLOW (সম্পূর্ণ সিস্টেম ফ্লো)

```
Company Setup → Configuration → Fiscal Year → Currency → Tax
→ Chart of Accounts → Opening Balances → Master Data → Inventory
→ Sales / Purchase → AR / AP → Cash & Bank → Expenses → Journal
→ Ledger → Trial Balance → Adjustments → Financial Statements
→ Period Closing → Year End Closing
```

---

# MODULE DEPENDENCY MAP (মডিউল ডিপেন্ডেন্সি ম্যাপ — ইউজার-ওয়ার্কফ্লো দৃষ্টিকোণ থেকে)

| মডিউল | নির্ভর করে | কারণ |
|---|---|---|
| Branch | Company | প্রতিটি Branch একটি Company-এর অধীনে |
| Users/Roles | Company, Branch | অ্যাক্সেস স্কোপ নির্ধারণে |
| Fiscal Year/Currency/Tax | Company | কোম্পানি-নির্দিষ্ট সেটিংস |
| Chart of Accounts | Company | অ্যাকাউন্ট স্ট্রাকচার কোম্পানি-নির্দিষ্ট |
| Customer/Supplier | CoA (AR/AP অ্যাকাউন্ট), Tax | প্রতিটি Customer/Supplier একটি কন্ট্রোল অ্যাকাউন্টের সাথে যুক্ত |
| Product | CoA (Sales/Purchase/Inventory/COGS), Tax | প্রতিটি Product-এ ডিফল্ট অ্যাকাউন্ট প্রয়োজন |
| Warehouse | Branch | স্টক শাখা-ভিত্তিক |
| General Journal | CoA, Fiscal Period | পোস্টের জন্য বৈধ অ্যাকাউন্ট ও খোলা পিরিয়ড দরকার |
| Sales | Customer, Product, Tax, Journal Engine | ইনভয়েস লিখতে গ্রাহক ও পণ্য দরকার, পোস্ট করতে Journal Engine দরকার |
| Purchase | Supplier, Product, Tax, Journal Engine | Sales-এর প্রতিসম |
| Inventory | Product, Warehouse, Journal Engine | স্টক আন্দোলনের সাথে অ্যাকাউন্টিং যুক্ত |
| Accounts Receivable | Sales, Journal | AR হলো Sales+Journal ডেটার ওপর একটি ভিউ |
| Accounts Payable | Purchase, Journal | AP হলো Purchase+Journal ডেটার ওপর একটি ভিউ |
| Cash & Bank | CoA, Journal Engine | সরাসরি অ্যাকাউন্টিং লেনদেন |
| Expense | CoA, Cash & Bank, Tax | পেমেন্ট মেথড হিসেবে Cash/Bank প্রয়োজন |
| Fixed Assets | CoA, Cash & Bank/Payable | ক্রয়ের জন্য পেমেন্ট সোর্স দরকার |
| Payroll | Employee ডেটা, CoA, Cash & Bank | বেতন প্রদানে Cash/Bank দরকার |
| Budget | CoA, Branch, Ledger Actuals | Actual তুলনার জন্য Ledger দরকার |
| Reports/Financial Statements | Ledger, সব লেনদেনমূলক মডিউল | সব ডেটার ওপর নির্ভরশীল |
| Dashboard | Reports, Financial Statements | সারাংশ প্রদর্শন |
| Approval Workflow | Users/Roles | Sales/Purchase/Journal/Expense/Payroll/Budget-কে র‍্যাপ করে |
| Audit Trail | সব মডিউল | সব মডিউলের কার্যক্রম রেকর্ড করে |
| Period Closing | Ledger, Financial Statements | ক্লোজিং-এর আগে সব ডেটা চূড়ান্ত হতে হবে |
| Year End Closing | Period Closing, Fixed Assets (Depreciation) | সব পিরিয়ড বন্ধ হওয়ার পরই বছর বন্ধ হয় |

---

# USER ROLE JOURNEYS (ইউজার রোল জার্নি)

## Super Admin

```
Login → Dashboard (সব কোম্পানি/শাখা) → সব মডিউল অ্যাক্সেসযোগ্য
```
দৈনিক কাজ: সিস্টেম-ওয়াইড সেটিংস তদারকি, নতুন কোম্পানি সেটআপে সহায়তা, ইউজার/রোল ম্যানেজমেন্ট। রিপোর্ট: সব ধরনের। সীমাবদ্ধতা: প্রায় কোনোটিই নেই, তবে অ্যাকাউন্টিং লেনদেন সাধারণত নিজে পোস্ট করেন না।

## Company Admin

```
Login → Dashboard (নিজ কোম্পানি) → প্রায় সব মডিউল, Settings-সহ
```
দৈনিক কাজ: হাই-লেভেল অ্যাপ্রুভাল, রিপোর্ট পর্যালোচনা, Period/Year End Closing সম্পন্ন করা। অ্যাপ্রুভাল: বড় অঙ্কের Sales/Purchase/Expense/Payroll চূড়ান্ত অনুমোদন। রিপোর্ট: সব Financial Statement ও অপারেশনাল রিপোর্ট। সীমাবদ্ধতা: অন্য কোম্পানির ডেটা দেখতে পারেন না।

## Accountant

```
Login → Dashboard → Accounting, Journal, Ledger, Reports, Period Closing
```
দৈনিক কাজ: Journal এন্ট্রি, Bank Reconciliation, Expense পর্যালোচনা, AR/AP মনিটরিং। মাস-শেষে: Period Closing চেকলিস্ট সম্পন্ন করা, Depreciation Run, Trial Balance পর্যালোচনা। অ্যাপ্রুভাল: মাঝারি অঙ্কের Journal/Expense। রিপোর্ট: সব অ্যাকাউন্টিং রিপোর্ট। সীমাবদ্ধতা: সাধারণত Payroll প্রসেস করেন না (আলাদা HR/Payroll Manager থাকলে)।

## Sales Executive

```
Login → Dashboard (সীমিত: শুধু নিজের Sales পারফরম্যান্স) → Sales মডিউল
```
দৈনিক কাজ: Quotation, Sales Order, Invoice তৈরি করা, Customer-এর সাথে যোগাযোগ, বকেয়া ফলো-আপ। অ্যাপ্রুভাল: সাধারণত নিজে অ্যাপ্রুভ করেন না, বরং তার তৈরি লেনদেন Approval-এর জন্য পাঠান। রিপোর্ট: শুধু Sales-সম্পর্কিত। সীমাবদ্ধতা: Purchase, Payroll, Journal-এ অ্যাক্সেস নেই; Posted Invoice এডিট করতে পারেন না।

## Purchase Executive

Sales Executive-এর প্রতিসম — শুধু Purchase মডিউল, Supplier যোগাযোগ, PO/GRN/Bill তৈরি।

## Inventory Manager

```
Login → Dashboard (স্টক-কেন্দ্রিক) → Inventory, Warehouse
```
দৈনিক কাজ: স্টক পর্যালোচনা, Low Stock এলার্ট হ্যান্ডেল করা, Stock Transfer/Adjustment করা, Physical Count-এর সাথে সিস্টেম মেলানো। রিপোর্ট: Inventory-সংক্রান্ত সব রিপোর্ট। সীমাবদ্ধতা: Sales/Purchase Invoice তৈরি করতে পারেন না, শুধু Stock Movement দেখতে/করতে পারেন।

## HR / Payroll Manager

```
Login → Dashboard (Payroll-কেন্দ্রিক) → Payroll মডিউল
```
দৈনিক কাজ: Employee তথ্য হালনাগাদ, Attendance/Leave রেকর্ড, মাস-শেষে Payroll Processing। অ্যাপ্রুভাল: নিজের প্রস্তুতকৃত Payroll Run সাধারণত Company Admin/Finance Head-এর কাছে পাঠান। রিপোর্ট: শুধু Payroll-সংক্রান্ত। সীমাবদ্ধতা: Sales/Purchase/Journal-এ অ্যাক্সেস নেই।

## Viewer

```
Login → Dashboard (শুধু পঠনযোগ্য) → অনুমোদিত মডিউলে শুধু View
```
দৈনিক কাজ: রিপোর্ট দেখা, স্টেটমেন্ট ডাউনলোড করা। অ্যাপ্রুভাল: কোনো অনুমতি নেই। সীমাবদ্ধতা: কোনো Create/Edit/Delete/Approve/Post বাটন দেখতে পান না — সব UI Read-only মোডে থাকে।

---

# DAILY / MONTHLY / YEARLY ACCOUNTING WORKFLOW

## দৈনিক (Daily)

- সকালে লগইন করে Dashboard-এ গতকালের Sales/Cash Position দেখা।
- নতুন Sales Invoice, Purchase Bill এন্ট্রি ও পোস্ট করা।
- আসা Payment/Receipt রেকর্ড করা।
- Pending Approval ইনবক্স চেক করে প্রয়োজনীয় অ্যাপ্রুভাল দেওয়া।
- Notification (Overdue Invoice, Low Stock ইত্যাদি) পর্যালোচনা করে প্রয়োজনীয় পদক্ষেপ নেওয়া।
- ছোটখাটো Expense এন্ট্রি ও পোস্ট করা।

## মাসিক (Monthly)

- সব দৈনিক লেনদেন সম্পূর্ণতা যাচাই।
- Cash ও Bank Reconciliation সম্পন্ন করা।
- AR/AP Aging পর্যালোচনা করে প্রয়োজনে গ্রাহক/সরবরাহকারীর সাথে যোগাযোগ।
- Inventory Count ও প্রয়োজনীয় Adjustment।
- মাসিক Payroll প্রসেস করা (যদি মাসিক বেতন প্রথা হয়)।
- Depreciation Run করা।
- Recurring/Accrual Journal পোস্ট করা।
- Tax/VAT রিপোর্ট পর্যালোচনা।
- Trial Balance ও Financial Statement জেনারেট ও পর্যালোচনা করা।
- Budget vs Actual পর্যালোচনা।
- **Phase 23 — Period Closing** সম্পন্ন করা।

## বার্ষিক (Yearly)

- সব ১২টি Period বন্ধ হয়েছে তা নিশ্চিত করা।
- বার্ষিক Adjustment, চূড়ান্ত Depreciation, Accrual, Tax Adjustment পোস্ট করা।
- চূড়ান্ত Trial Balance ও পূর্ণাঙ্গ Financial Statements (P&L, Balance Sheet, Cash Flow, Equity পরিবর্তন বিবরণী) পর্যালোচনা।
- Net Profit/Loss গণনা ও Closing Entries জেনারেট করা।
- Retained Earnings হালনাগাদ করা।
- বর্তমান Fiscal Year বন্ধ করা।
- নতুন Fiscal Year তৈরি করা এবং Opening Balance স্বয়ংক্রিয়ভাবে বহন করা।
- নতুন বছরের জন্য Budget প্রস্তুত করা।
- বার্ষিক নিরীক্ষা (External Audit) থাকলে Audit Trail ও সব Attachment প্রস্তুত রাখা।

---

# হাতে-কলমে ডেটা এন্ট্রি টেস্ট গাইড (Manual Data-Entry Workbook)

> এই অধ্যায়টি **সাধারণ ব্যবহারকারীর জন্য ধাপে-ধাপে হাতে-কলমে পরীক্ষা-পদ্ধতি**। ধরুন আপনি একটি
> ডেমো কোম্পানিতে (যেমন `Admin Business`) বসে আছেন এবং ডেমো সুপার-অ্যাডমিন লগইন করেছেন:
> **ইমেইল:** `admin@demobusiness.local`, **পাসওয়ার্ড:** `password`, **URL:** `http://localhost:8000`।
>
> **ফ্রেশ ডেটাবেস থেকে শুরু করতে** প্রথমে `php artisan migrate:fresh --seed` চালান (নিচের
> **"শুরু — ডেটাবেস সেটআপ, সিডিং ও টেস্ট ফ্লো"** অধ্যায় দেখুন) — COA, ট্যাক্স, কারেন্সি, FY/Period,
> প্রোডাক্ট ক্যাটাগরি ও একক, এক্সপেন্স/অ্যাসেট/পে-রোল ক্যাটাগরি **সিড হয়ে থাকবে**। শুধু গ্রাহক,
> সরবরাহকারী, পণ্য, গুদাম ও লেনদেন নিচের ধাপে হাতে বানাতে হবে।
>
> প্রতিটি ধাপে বলা আছে — **কোন মেনুতে ক্লিক করবেন**, **কী টাইপ করবেন**, **কোন বাটন চাপবেন**, এবং
> **কীভাবে মিলিয়ে দেখবেন সঠিক হয়েছে কিনা**। বক্সে `[x]` চিহ্ন দেওয়ার সাথে সাথে এগিয়ে যান।
> (নতুন সাইডবার টপিক-গ্রুপ ভাঁজ করা/খোলা যায় — কোনো মেনু না পাওয়া গেলে উপরের গ্রুপ টাইটেলে ক্লিক করুন।)

---

## পর্ব ১ — লগইন ও ড্যাশবোর্ড যাচাই

1. [ ] ব্রাউজারে `http://localhost:8000/login` খুলুন।
2. [ ] ইমেইল ফিল্ডে `admin@demobusiness.local`, পাসওয়ার্ড ফিল্ডে `password` দিন। **Login** বাটনে ক্লিক করুন।
3. [ ] ড্যাশবোর্ড খুলবে — উপরে কোম্পানির নাম ও মুদ্রা (`Admin Business • BDT`) দেখা যাবে।
4. [ ] শীর্ষে ৬টি KPI কার্ড থাকবে: Income, Expense, Net, Cash Balance, AR, AP — সব `0.00` বা সিডার ডেটা অনুযায়ী।
5. [ ] বাম সাইডবার **Overview** গ্রুপ খোলা আছে, **Dashboard** আইটেমে ক্লিক করুন — URL `/dashboard`।
6. [ ] সাইডবার স্ক্রল করে নিচে যান, অন্য মেনুতে ক্লিক করুন — স্ক্রল পজিশন আগের মতোই থাকবে (ছোটখাটো UX ফিক্স)।

## পর্ব ২ — মাস্টার ডেটা তৈরি

> ফ্রেশ ডেটাবেসে ক্যাটাগরি (Goods/Services/Raw Materials), একক (pc/kg/L/bx/hr), FY/Period,
> COA ও ট্যাক্স ইতিমধ্যে সিড করা — এগুলো বানাতে হবে না। **হাতে বানাতে হবে: গুদাম, গ্রাহক, সরবরাহকারী, পণ্য।**

### গুদাম তৈরি (প্রথমে)

0. [ ] **Master Data → Warehouses** (`/warehouses`) → **New Warehouse** → নাম: `Main Warehouse`, **save** → স্ট্যাটাস `Active`।
   (ইনভেন্টরি মুভমেন্ট প্রথম সক্রিয় গুদামেই জমা হয় — অন্তত একটি থাকতেই হবে।)

### গ্রাহক তৈরি

1. [ ] সাইডবার **Master Data → Customers**-এ ক্লিক করুন (`/customers`)।
2. [ ] **New Customer** বাটনে ক্লিক করুন (`/customers/create`)।
3. [ ] ম্যানুয়াল ফিল্ড — নাম: `Alpha Traders`, ইমেইল: `alpha@example.com`, ফোন: `01700000001`।
4. [ ] **Save** বাটনে ক্লিক করুন। তালিকায় `Alpha Traders` দেখা যাবে।

### সরবরাহকারী তৈরি

5. [ ] **Suppliers** → **New Supplier** → নাম: `Omega Supplies`, ইমেইল: `omega@example.com`, **Save**।

### পণ্য ও সার্ভিস

6. [ ] **Products & Services** → **New Product** → নাম: `Widget A`, ধরন: `Product`, কোড: `WID-001`।
7. [ ] বিক্রয় মূল্য (Sales Price): `150.00`, ক্রয় মূল্য (Purchase Price): `100.00`।
8. [ ] **Track Inventory** চেকবক্সে **পূর্বে** টিক দিন → একক ও গুদাম ফিল্ড আসবে → একক: `pc`, গুদাম: প্রথম সক্রিয় গুদাম।
9. [ ] **Create** → তালিকায় `Widget A` (কোড `WID-001`)।

## পর্ব ৩ — চার্ট অব অ্যাকাউন্টস ও ট্যাক্স যাচাই

1. [ ] **Accounting → Chart of Accounts** (`/accounts`) — ডেফল্ট COA লোড হয়েছে (কোড 1111 ক্যাশ, 1201 AR, 4111 বিক্রয় পূর্ব পর্যন্ত খোলে/ভাঁজ হয়)।
2. [ ] **Tax & VAT** (`/tax`) — `VAT 15%` ধরনের ট্যাক্স আছে; এটির আউটপুট অ্যাকাউন্ট `VAT Payable`, ইনপুট অ্যাকাউন্ট `VAT Receivable` বলে যাচাই করুন।
3. [ ] **Accounting Settings** (`/accounting-settings`) — Default AR, AP, Cash, Bank, Inventory, Sales, Purchase অ্যাকাউন্ট সব নন-শূন্য কী না দেখুন।

## পর্ব ৪ — চার্টে লেনদেন: Purchase Bill (ক্রয়)

> পরীক্ষা-মূল্য: ২০ পিস `Widget A` @ ১০০ = **২,০০০** + ১৫% ভ্যাট = **৩০০** → মোট **২,৩০০**।

1. [ ] **Purchase → Purchase Bills** (`/purchase/bills`) → **New Bill**।
2. [ ] সরবরাহকারী: `Omega Supplies`; তারিখ: সক্রিয় পিরিয়ডের ভেতরে (যেমন চলতি মাসের যেকোনো দিন)।
3. [ ] পণ্য: `Widget A` — ইউনিট কস্ট `100.00` নিজে আসবে; পরিমাণ `20`; ট্যাক্স `VAT 15%`; লাইন টোটাল `2,300.00` (ফর্ম নিজে হিসাব করে)।
4. [ ] **Save as Draft** → নম্বর খালি (ড্রাফট) থাকবে, স্ট্যাটাস `Draft`।
5. [ ] **Post** বাটনে ক্লিক করুন → স্ট্যাটাস `Posted`, বিল নম্বর `PB-{বছর}-0001`।
6. [ ] **View Bill** লিঙ্কে ক্লিক করে পোস্টেড জার্নাল দেখুন — ৩টি লাইন:
   | ডেবিট | ক্রেডিট |
   |---|---|
   | Inventory ড্র 2,000 | |
   | Input VAT ড্র 300 | |
   | | Accounts Payable ক্র 2,300 |
7. [ ] **Inventory → Stock** (`/inventory/stock`) — `Widget A`-এর On-hand **20**, গড় খরচ **100.00**, মূল্য **2,000.00**।

## পর্ব ৫ — Sales Invoice (বিক্রয়) ও Receipt (আদায়)

> পরীক্ষা-মূল্য: ১০ পিস `Widget A` @ ১৫০ = **১,৫০০** + ১৫% ভ্যাট = **২২৫** → মোট **১,৭২৫**।

1. [ ] **Sales → Sales Invoices** (`/sales/invoices`) → **New Invoice**।
2. [ ] গ্রাহক: `Alpha Traders`; তারিখ: সক্রিয় পিরিয়ডের ভেতরে।
3. [ ] পণ্য: `Widget A` — ইউনিট মূল্য `150.00` নিজে আসবে; পরিমাণ `10`; ট্যাক্স `VAT 15%`; মোট `1,725.00`।
4. [ ] **Save as Draft** → স্ট্যাটাস `Draft`। **Post** → `Posted`, নম্বর `SL-{বছর}-0001`।
5. [ ] **View Invoice** লিঙ্ক থেকে পোস্টেড জার্নাল যাচাই করুন:
   | ডেবিট | ক্রেডিট |
   |---|---|
   | AR ড্র 1,725 | |
   | | Sales Revenue ক্র 1,500 |
   | | Output VAT ক্র 225 |
   | COGS ড্র 1,000 | |
   | | Inventory ক্র 1,000 |
6. [ ] **Inventory → Stock** — On-hand **10** (২০ − ১০), মূল্য **1,000.00**, গড় খরচ **100.00** (ওজন গড় পদ্ধতি)।
7. [ ] ইনভয়েস **Show** পেজে **Record Payment** বাটন → মডালে: পেমেন্ট অ্যাকাউন্ট `1111 Cash`, পরিমাণ `1,725.00`, তারিখ আজ → **Save**। স্ট্যাটাস `Paid` হয়ে যাবে, `amount_paid` = 1,725।
8. [ ] **Receivables → Outstanding** (`/receivables/outstanding`) — Alpha Traders-এর ব্যালেন্স `0.00` (পেইড)।

## পর্ব ৬ — Supplier Payment (পরিশোধ)

1. [ ] **Payables → Record Payment** (`/payables/record-payment`)।
2. [ ] সরবরাহকারী: `Omega Supplies`; পেমেন্ট অ্যাকাউন্ট `1111 Cash`; পরিমাণ `2,300.00` (ব্যালেন্স ডিউ) → **Record Payment**।
3. [ ] **Journals** (`/journals`) খুলে সর্বশেষ PMT জার্নাল দেখুন — AP ড্র 2,300 | Cash ক্র 2,300।
4. [ ] **Payables → Outstanding** — Omega Supplies ব্যালেন্স `0.00`।

## পর্ব ৭ — Expense ও Cash/Bank

### এক্সপেন্স

1. [ ] **Expenses** (`/expenses`) → **New Expense**।
2. [ ] পেমেন্ট মেথড **প্রথমে** `Cash` বাছুন → ক্যাশ অ্যাকাউন্ট আসবে।
3. [ ] ক্যাটাগরি: `Office Supplies`; পেয়ি: `Office Depot`; তারিখ আজ; পরিমাণ `500.00` → **Save as Draft**।
4. [ ] **Post** → নম্বর `EXP-{বছর}-0001`; জার্নাল: Office Supplies ড্র 500 | Cash ক্র 500।
5. [ ] **Cash & Bank** (`/cash-bank`) → **Transactions** ট্যাবে — Cash-এ ক্রেডিট 500, ব্যাংক ব্যালেন্স অপরিবর্তিত।

### সরাসরি ব্যাংক লেনদেন

6. [ ] **Cash & Bank → Transactions** → **New Transaction** → ধরন `Bank Deposit`, ক্যাশ থেকে ব্যাংক `1112`-এ ২,০০০ দিন → **Save**। CBT নম্বর ও জার্নাল (Bank ড্র 2000 | Cash ক্র 2000) দেখুন।

## পর্ব ৮ — Fixed Asset ও Depreciation

1. [ ] **Fixed Assets** (`/fixed-assets`) → **New Asset** → নাম: `Office Desk`, ক্যাটাগরি: `Furniture, Fixtures & Computers`, ক্রয় মূল্য `50,000`, তারিখ আজ, মেথড: ক্যাটাগরি থেকে আসবে → **Save**।
2. [ ] Show পেজে **Capitalize** → `FA-{বছর}-0001` জার্নাল: Fixed Asset ড্র 50,000 | Cash ক্র 50,000।
3. [ ] Index পেজে **Run Depreciation** → পিরিয়ড বাছুন → **Run** → DEP জার্নাল তৈরি: Dep Expense ড্র | Accumulated Depreciation ক্র (২৪ মাসের স্ট্রেইট-লাইন = 50,000/36)।

## পর্ব ৯ — Payroll (বেতন)

1. [ ] **Payroll** (`/payroll`) → **Employees** ট্যাব → **Add Employee**: নাম `Rahim`, ডিপার্টমেন্ট `Admin`, ডিজাইনেশন `Manager`, যোগদান আজ।
2. [ ] কর্মচারীর কার্ডে **Salary Structure**: Basic 30,000; HRA 10,000; Medical 5,000; Travel 5,000; Income Tax 2,000; PF 1,500 → Gross **50,000**, Net **46,500** (ফর্মে নিজে আসবে)।
3. [ ] **Payroll Runs** ট্যাবে ফেরত → পিরিয়ড বাছুন → **Process Payroll** → ড্রাফট রান। **Post** → `PR-{বছর}-0001`।
4. [ ] জার্নাল যাচাই (View Payroll Run → View Journal): Salary Expense ড্র 50,000 | Salary Payable ক্র 46,500 | Deductions Payable ক্র 3,500।
5. [ ] **Record Salary Payment**: মেথড `Cash`, পরিমাণ `46,500` → `SP-{বছর}-0001`।

## পর্ব ১০ — জেনারেল জার্নাল ও Budget

1. [ ] **Transactions → Journals** (`/journals`) → **New Journal** → ২টি লাইন: তারিখ আজ; (ক) Debit `Advertising Expense` ১,০০০; (খ) Credit `Cash` ১,০০০ → **Save Draft** → **Post** → `GJ-{বছর}-0001`।
2. [ ] **Budgets** (`/budgets`) → **New Budget** → নাম `FY-2026 Operating`, ফিসক্যাল ইয়ার বাছুন → লাইন যোগ করুন: অ্যাকাউন্ট `Advertising Expense`, পিরিয়ড ১, পরিমাণ `12,000` → **Save** → **Post** (লক হয়ে যাবে, Edit বন্ধ)।
3. [ ] Budget Show পেজে Variance টেবিল — Actual = পোস্টেড জার্নাল অনুযায়ী।

## পর্ব ১১ — AR/AP রিপোর্ট ও ক্যাশ রিকনসিলিয়েশন

1. [ ] **Receivables → Aging** — Alpha Traders (পেইড) ০। নতুন করে ৫০০-এর অন-পেইড ইনভয়েস করলে Current কলামে ৫০০ দেখাবে।
2. [ ] **Payables → Aging** — একইভাবে যাচাই।
3. [ ] **Cash & Bank → Reconciliation**: ব্যাংক 1112-এর ব্যালেন্সে ডিপোজিট ২,০০০ প্রতিফলিত; স্টেটমেন্ট CSV আপলোড করে `date,description,amount` ফরম্যাটে ২,০০০-এর লাইন দিলে Auto-match হবে → **Complete Reconciliation** (সব লাইন Match) → ব্যাংক Last Reconciled আপডেট।

## পর্ব ১২ — রিপোর্ট ও ফাইন্যান্সিয়াল স্টেটমেন্ট

1. [ ] **Reporting → Reports** (`/reports`) — **General Ledger** ট্যাব: অ্যাকাউন্ট বাছুন `Cash` → পোস্টেড এন্ট্রিগুলো লাইনের পর লাইন। **Trial Balance** ট্যাব: Debit/Credit দুই পাশ **সমান** (Balanced ব্যাজ)।
2. [ ] **Reporting → Statements** (`/statements`) —
   - **Profit & Loss**: Net Income = Sales 1,500 − COGS 1,000 − Office Supplies 500 − Advertising 1,000 − Depreciation (50,000/36≈1,389) = **−1,389** (ক্ষতি)।
   - **Balance Sheet**: Assets = Liabilities + Equity (Difference ±০.০১)।
   - **Cash Flow** ও **Equity** ট্যাবও রেন্ডার হচ্ছে কিনা দেখুন।

## পর্ব ১৩ — অ্যাপ্রুভাল (গেটেড পোস্টিং) টেস্ট

1. [ ] **Governance → Approval Workflows** (`/approval-workflows`) → **New Workflow**: মডিউল `expense`, মিন অ্যামাউন্ট `0`, ম্যাক্স `999999`, অ্যাপ্রুভার রোল `Accountant`, সিকোয়েন্স `1`, সক্রিয় → **Save**।
2. [ ] নতুন Expense ড্রাফ্ট তৈরি করুন (যেমন ২৫০) এবং **Post** চাপুন → **ব্লকড**, ফ্ল্যাশে মেসেজ: অ্যাপ্রুভে পাঠানো হয়েছে।
3. [ ] **Governance → Approvals** (`/approvals`) → pending ক্যাশ সারি → **Approve** → Expense আবার **Post** করলে এবার সফল।
4. [ ] (ঐচ্ছিক) **Reject** পথে: আরেকটি expense → **Reject** → আবার Submit করলে নতুন request তৈরি হয়।
5. [ ] **Notifications** (বেল আইকনে আনরিড ব্যাজ) — অ্যাপ্রুভের সাথে সাথে ব্যবহারকারীকে নোটিফিকেশনে তথ্য দেখাবে।

## পর্ব ১৪ — অডিট, ডকুমেন্ট ও ক্লোজিং

1. [ ] **Governance → Audit Log** (`/audit`) — উপরের সব Create/Post-এর লগ আছে; Details-এ Before/After ডিফ।
2. [ ] যে-কোনো Posted Invoice-এ **Attachments** — ফাইল আপলোড (যেমন `invoice.pdf`) → Download/Remove যাচাই।
3. [ ] **Accounting → Accounting Periods** — সক্রিয় পিরিয়ডের **Close** >> **Lock**: আগে আগের পিরিয়ড খোলা থাকলে "Close earlier periods first" সতর্কবার্তা।
4. [ ] সব পিরিয়ড বন্ধ করে **Fiscal Years** → **Close Fiscal Year** → YEC জার্নাল (Retained Earnings-এ Net P&L বহন) তৈরি; পরের FY থাকলে OB জার্নালে Asset/Liability বহন।
5. [ ] **Journals**-এ `YEC`, `OB`, `GJ`, `SINV`, `PUR`, `RCT`, `PMT`, `EXP`, `CBT`, `FA`, `DEP`, `PYR` প্রিফিক্সের জার্নালগুলো ধাপে ধাপে দেখা যায়।

## পর্ব ১৫ — চূড়ান্ত যাচাই চেকলিস্ট

- [ ] সব পোস্ট জার্নাল **Debit = Credit** (Trial Balance Balanced)।
- [ ] Inventory ট্র্যাকিংয়ে **Stock Value** = GL-এর Inventory জার্নাল ব্যালেন্স।
- [ ] AR ব্যালেন্স = সব Posted Invoice বাকি; AP ব্যালেন্স = সব Posted Bill বাকি।
- [ ] Cash & Bank অ্যাকাউন্ট ব্যালেন্স = সংগৃহীত সব CBT লেনদেনের নিট।
- [ ] Payroll Payable, VAT Payable/Receivable ব্যালেন্স ট্রায়াল ব্যালেন্সে প্রতিফলিত।
- [ ] সব গুরুত্বপূর্ণ অ্যাকশন Audit Log-এ নথিভুক্ত।
- [ ] সাইডবারে কোনো ভাঙা লিংক নেই; পেজ ঘুরতে কোনো ভুল/ক্র্যাশ নেই (কনসোলে ০ এরর)।

---

*এই ডকুমেন্ট প্রোডাক্ট ওয়ার্কফ্লো ব্লুপ্রিন্ট হিসেবে তৈরি — এখানে বর্ণিত প্রতিটি স্ক্রিন, বাটন, ফর্ম-ফিল্ড এবং স্ট্যাটাস-ট্রানজিশন ডিজাইনার UI ডিজাইনের জন্য, ডেভেলপার সঠিক ইউজার-ফ্লো বাস্তবায়নের জন্য, এবং QA ইঞ্জিনিয়ার এন্ড-টু-এন্ড টেস্ট-কেস তৈরির জন্য সরাসরি ব্যবহার করতে পারবেন।*
