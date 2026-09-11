<?php

namespace App\Domain\Purchase\Http\Controllers;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Party\Models\Supplier;
use App\Domain\Product\Models\Product;
use App\Domain\Purchase\Exceptions\PurchasePostingException;
use App\Domain\Purchase\Http\Requests\PurchaseBillRequest;
use App\Domain\Purchase\Http\Requests\SupplierPaymentRequest;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\PurchaseBillLine;
use App\Domain\Purchase\Services\PurchaseBillService;
use App\Domain\Tax\Models\TaxRate;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseBillController
{
    public function __construct(
        protected PurchaseBillService $service
    ) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $bills = PurchaseBill::query()
            ->with('supplier:id,name,code')
            ->where('company_id', $companyId)
            ->when($request->input('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(fn ($q) => $q
                    ->where('bill_no', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%")));
            })
            ->when($request->input('status'), fn ($q, $status) => $this->applyStatusFilter($q, $status))
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (PurchaseBill $bill) {
                return [
                    'id' => $bill->id,
                    'bill_no' => $bill->bill_no,
                    'bill_date' => $bill->bill_date?->toDateString(),
                    'due_date' => $bill->due_date?->toDateString(),
                    'reference' => $bill->reference,
                    'supplier_id' => $bill->supplier_id,
                    'supplier' => $bill->supplier ? [
                        'id' => $bill->supplier->id,
                        'code' => $bill->supplier->code,
                        'name' => $bill->supplier->name,
                    ] : null,
                    'subtotal' => (float) $bill->subtotal,
                    'discount_amount' => (float) $bill->discount_amount,
                    'tax_amount' => (float) $bill->tax_amount,
                    'total' => (float) $bill->total,
                    'amount_paid' => (float) $bill->amount_paid,
                    'status' => $bill->status,
                    'paid_state' => $bill->paidState(),
                    'is_overdue' => $bill->isOverdue(),
                ];
            });

        return Inertia::render('Purchase/Bills/Index', [
            'bills' => $bills,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Purchase/Bills/Create', [
            'suppliers' => $this->supplierOptions(),
            'products' => $this->productOptions(),
            'taxRates' => $this->taxRateOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(PurchaseBillRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyId = current_company_id();

        $payload = $this->buildTotals($data);

        $bill = PurchaseBill::query()->create($payload + [
            'company_id' => $companyId,
            'branch_id' => session('active_branch_id'),
            'supplier_id' => $data['supplier_id'],
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => TransactionStatus::Draft->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->saveLines($bill, $data['lines']);
        $bill->load('lines');

        AuditLogger::log('purchase_bill', 'create', null, $bill->id, [], $bill->fresh()->load('lines')->toArray(), $companyId);

        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('success', 'Bill draft saved.');
    }

    public function show(PurchaseBill $bill): Response
    {
        $this->authorizeBill($bill);

        $bill->load([
            'supplier:id,name,code,email,phone,address',
            'lines.product:id,sku,name',
            'lines.taxRate:id,name,rate_percent',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
        ]);

        $journal = $bill->journal()->first();

        return Inertia::render('Purchase/Bills/Show', [
            'bill' => [
                'id' => $bill->id,
                'bill_no' => $bill->bill_no,
                'bill_date' => $bill->bill_date->toDateString(),
                'due_date' => $bill->due_date?->toDateString(),
                'reference' => $bill->reference,
                'notes' => $bill->notes,
                'status' => $bill->status,
                'paid_state' => $bill->paidState(),
                'overdue' => $bill->isOverdue(),
                'subtotal' => (float) $bill->subtotal,
                'discount_amount' => (float) $bill->discount_amount,
                'tax_amount' => (float) $bill->tax_amount,
                'total' => (float) $bill->total,
                'amount_paid' => (float) $bill->amount_paid,
                'balance_due' => round($bill->balanceDue(), 4),
                'posted_at' => $bill->posted_at?->toDateTimeString(),
                'supplier' => $bill->supplier,
                'lines' => $bill->lines->map(fn (PurchaseBillLine $line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product?->name ?? $line->description,
                    'product_sku' => $line->product?->sku,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                    'discount_amount' => (float) $line->discount_amount,
                    'tax_rate_name' => $line->taxRate?->name,
                    'tax_rate_percent' => (float) $line->tax_rate_percent,
                    'tax_amount' => (float) $line->tax_amount,
                    'line_total' => (float) $line->line_total,
                ]),
                'payments' => $bill->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'payment_no' => $payment->payment_no,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'reference' => $payment->reference,
                    'memo' => $payment->memo,
                    'account_name' => $payment->account?->name,
                    'amount' => (float) $payment->pivot->amount,
                    'journal_id' => $payment->journal?->id,
                    'journal_no' => $payment->journal?->journal_no,
                ]),
                'journal_id' => $journal?->id,
                'journal_no' => $journal?->journal_no,
            ],
            'accounts' => $this->paymentAccountOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function edit(PurchaseBill $bill): Response
    {
        $this->authorizeBill($bill);

        abort_if(! $bill->isDraft(), 422, 'Only draft bills can be edited.');

        $bill->load('lines');

        return Inertia::render('Purchase/Bills/Edit', [
            'bill' => [
                'id' => $bill->id,
                'supplier_id' => $bill->supplier_id,
                'bill_date' => $bill->bill_date->toDateString(),
                'due_date' => $bill->due_date?->toDateString(),
                'reference' => $bill->reference,
                'notes' => $bill->notes,
            ],
            'lines' => $bill->lines->map(fn (PurchaseBillLine $line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'discount_amount' => (float) $line->discount_amount,
                'tax_rate_id' => $line->tax_rate_id,
            ]),
            'suppliers' => $this->supplierOptions(),
            'products' => $this->productOptions(),
            'taxRates' => $this->taxRateOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function update(PurchaseBillRequest $request, PurchaseBill $bill): RedirectResponse
    {
        $this->authorizeBill($bill);

        abort_if(! $bill->isDraft(), 422, 'Only draft bills can be updated.');

        $old = $bill->load('lines')->toArray();
        $data = $request->validated();
        $payload = $this->buildTotals($data);

        $bill->update([
            'supplier_id' => $data['supplier_id'],
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $payload['subtotal'],
            'discount_amount' => $payload['discount_amount'],
            'tax_amount' => $payload['tax_amount'],
            'total' => $payload['total'],
            'updated_by' => Auth::id(),
        ]);

        $bill->lines()->delete();
        $this->saveLines($bill, $data['lines']);

        AuditLogger::log('purchase_bill', 'update', null, $bill->id, $old, $bill->fresh()->load('lines')->toArray(), $bill->company_id);

        return redirect()
            ->route('purchase.bills.show', $bill)
            ->with('success', 'Bill draft updated.');
    }

    public function post(PurchaseBill $bill): RedirectResponse
    {
        $this->authorizeBill($bill);

        try {
            $bill = $this->service->postBill($bill);

            return redirect()
                ->route('purchase.bills.show', $bill)
                ->with('success', "Bill {$bill->bill_no} posted.");
        } catch (PurchasePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pay(SupplierPaymentRequest $request, PurchaseBill $bill): RedirectResponse
    {
        $this->authorizeBill($bill);

        try {
            $payment = $this->service->recordPayment($bill, $request->validated());

            AuditLogger::log('supplier_payment', 'create', null, $payment->id, [], $payment->toArray(), $bill->company_id);

            return redirect()
                ->route('purchase.bills.show', $bill)
                ->with('success', "Payment {$payment->payment_no} posted — payment recorded.");
        } catch (PurchasePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(PurchaseBill $bill): RedirectResponse
    {
        $this->authorizeBill($bill);

        abort_if(! $bill->isDraft(), 422, 'Only draft bills can be deleted.');

        $companyId = $bill->company_id;
        $bill->delete();

        AuditLogger::log('purchase_bill', 'delete', null, $bill->id, [], [], $companyId);

        return redirect()
            ->route('purchase.bills.index')
            ->with('success', 'Draft bill deleted.');
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function authorizeBill(PurchaseBill $bill): void
    {
        abort_if($bill->company_id !== current_company_id(), 403, 'This bill belongs to a different company.');
    }

    private function buildTotals(array $data): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($data['lines'] as $line) {
            $computed = $this->lineComputed($line);

            $subtotal += $computed['line_total'];
            $discount += $computed['discount_amount'];
            $tax += $computed['tax_amount'];
        }

        return [
            'subtotal' => round($subtotal, 4),
            'discount_amount' => round($discount, 4),
            'tax_amount' => round($tax, 4),
            'total' => round($subtotal + $tax, 4),
        ];
    }

    private function saveLines(PurchaseBill $bill, array $lines): void
    {
        $rows = [];

        foreach ($lines as $line) {
            $product = Product::query()->find((int) $line['product_id']);
            $computed = $this->lineComputed($line);
            $taxRateId = ! empty($line['tax_rate_id']) ? (int) $line['tax_rate_id'] : ($product?->tax_rate_id);

            $rows[] = [
                'product_id' => (int) $line['product_id'],
                'description' => $line['description'] ?? $product?->name,
                'quantity' => (float) $line['quantity'],
                'unit_cost' => (float) $line['unit_cost'],
                'discount_amount' => $computed['discount_amount'],
                'tax_rate_id' => $taxRateId ?: null,
                'tax_rate_percent' => $computed['tax_percent'],
                'tax_amount' => $computed['tax_amount'],
                'line_total' => $computed['line_total'],
            ];
        }

        $bill->lines()->createMany($rows);
    }

    /** Recompute a line's money from the request so totals never trust client math. */
    private function lineComputed(array $line): array
    {
        $quantity = (float) $line['quantity'];
        $unitCost = (float) $line['unit_cost'];
        $discountAmount = round((float) ($line['discount_amount'] ?? 0), 4);

        $lineTotal = round(max($quantity * $unitCost - $discountAmount, 0), 4);

        $rateId = ! empty($line['tax_rate_id']) ? (int) $line['tax_rate_id'] : 0;
        $taxPercent = 0.0;

        if ($rateId && ($rate = TaxRate::query()->find($rateId))) {
            $taxPercent = (float) $rate->rate_percent;
        } elseif (! $rateId && ($product = Product::query()->find((int) $line['product_id'])) && $product->tax_rate_id) {
            $taxPercent = (float) (TaxRate::query()->find($product->tax_rate_id)?->rate_percent ?? 0);
        }

        return [
            'line_total' => $lineTotal,
            'discount_amount' => $discountAmount,
            'tax_percent' => $taxPercent,
            'tax_amount' => round($lineTotal * $taxPercent / 100, 4),
        ];
    }

    /** Apply a derived payment-state filter on top of the raw status. */
    private function applyStatusFilter($query, string $status)
    {
        return match ($status) {
            'draft', 'posted' => $query->where('status', $status),
            'unpaid' => $query->where('status', 'posted')
                ->where('amount_paid', '<=', 0)
                ->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', now()->toDateString())),
            'partial' => $query->where('status', 'posted')
                ->where('amount_paid', '>', 0)
                ->whereColumn('amount_paid', '<', 'total'),
            'paid' => $query->where('status', 'posted')->whereColumn('amount_paid', '>=', 'total'),
            'overdue' => $query->where('status', 'posted')
                ->whereColumn('amount_paid', '<', 'total')
                ->whereDate('due_date', '<', now()->toDateString()),
            default => $query,
        };
    }

    private function supplierOptions(): array
    {
        $companyId = current_company_id();

        $outstanding = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->select('supplier_id', DB::raw('SUM(total) - SUM(amount_paid) as balance'))
            ->groupBy('supplier_id')
            ->pluck('balance', 'supplier_id');

        return Supplier::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'payment_terms_days'])
            ->map(fn (Supplier $supplier) => [
                'value' => $supplier->id,
                'label' => $supplier->code ? "{$supplier->code} — {$supplier->name}" : $supplier->name,
                'payment_terms_days' => (int) $supplier->payment_terms_days,
                'outstanding' => round((float) ($outstanding[$supplier->id] ?? 0), 4),
            ])
            ->all();
    }

    private function productOptions(): array
    {
        return Product::query()
            ->where('company_id', current_company_id())
            ->where('is_active', true)
            ->orderBy('name')
            ->with('unit:id,name,symbol')
            ->get(['id', 'sku', 'name', 'type', 'sales_price', 'purchase_price', 'tax_rate_id', 'unit_id'])
            ->map(fn (Product $product) => [
                'value' => $product->id,
                'label' => $product->sku ? "{$product->name} ({$product->sku})" : $product->name,
                'type' => $product->type,
                'purchase_price' => (float) $product->purchase_price,
                'tax_rate_id' => $product->tax_rate_id,
                'unit' => $product->unit?->symbol,
            ])
            ->all();
    }

    private function taxRateOptions(): array
    {
        return TaxRate::query()
            ->where('company_id', current_company_id())
            ->orderBy('name')
            ->get(['id', 'name', 'rate_percent', 'is_inclusive'])
            ->map(fn (TaxRate $rate) => [
                'value' => $rate->id,
                'label' => "{$rate->name} ({$rate->rate_percent}%)",
                'rate_percent' => (float) $rate->rate_percent,
                'is_inclusive' => (bool) $rate->is_inclusive,
            ])
            ->all();
    }

    private function paymentAccountOptions(): array
    {
        $setting = AccountingSetting::query()->where('company_id', current_company_id())->first();

        $accounts = Account::query()
            ->where('company_id', current_company_id())
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where(fn ($q) => $q->whereIn('id', array_filter([
                $setting?->default_cash_account_id,
                $setting?->default_bank_account_id,
            ]))->orWhereIn('code', ['1111', '1112', '1113']))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $account) => ['value' => $account->id, 'label' => "{$account->code} — {$account->name}"]);

        // Fallback: all postable accounts if nothing matched the cash/bank defaults.
        if ($accounts->isEmpty()) {
            return Account::query()
                ->where('company_id', current_company_id())
                ->where('is_active', true)
                ->where('is_postable', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Account $account) => ['value' => $account->id, 'label' => "{$account->code} — {$account->name}"])
                ->all();
        }

        return $accounts->all();
    }
}