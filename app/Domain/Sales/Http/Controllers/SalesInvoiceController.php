<?php

namespace App\Domain\Sales\Http\Controllers;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Party\Models\Customer;
use App\Domain\Product\Models\Product;
use App\Domain\Sales\Exceptions\SalesPostingException;
use App\Domain\Sales\Http\Requests\ReceiptRequest;
use App\Domain\Sales\Http\Requests\SalesInvoiceRequest;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Sales\Models\SalesInvoiceLine;
use App\Domain\Sales\Services\SalesInvoiceService;
use App\Domain\Tax\Models\TaxRate;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesInvoiceController
{
    public function __construct(
        protected SalesInvoiceService $service
    ) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $invoices = SalesInvoice::query()
            ->with('customer:id,name,code')
            ->where('company_id', $companyId)
            ->when($request->input('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(fn ($q) => $q
                    ->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%")));
            })
            ->when($request->input('status'), fn ($q, $status) => $this->applyStatusFilter($q, $status))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (SalesInvoice $invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'invoice_date' => $invoice->invoice_date?->toDateString(),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'reference' => $invoice->reference,
                    'customer_id' => $invoice->customer_id,
                    'customer' => $invoice->customer ? [
                        'id' => $invoice->customer->id,
                        'code' => $invoice->customer->code,
                        'name' => $invoice->customer->name,
                    ] : null,
                    'subtotal' => (float) $invoice->subtotal,
                    'discount_amount' => (float) $invoice->discount_amount,
                    'tax_amount' => (float) $invoice->tax_amount,
                    'total' => (float) $invoice->total,
                    'amount_paid' => (float) $invoice->amount_paid,
                    'status' => $invoice->status,
                    'paid_state' => $invoice->paidState(),
                    'is_overdue' => $invoice->isOverdue(),
                ];
            });

        return Inertia::render('Sales/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sales/Invoices/Create', [
            'customers' => $this->customerOptions(),
            'products' => $this->productOptions(),
            'taxRates' => $this->taxRateOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(SalesInvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyId = current_company_id();

        $payload = $this->buildTotals($data);

        $invoice = SalesInvoice::query()->create($payload + [
            'company_id' => $companyId,
            'branch_id' => session('active_branch_id'),
            'customer_id' => $data['customer_id'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => TransactionStatus::Draft->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->saveLines($invoice, $data['lines']);
        $invoice->load('lines');

        AuditLogger::log('sales_invoice', 'create', null, $invoice->id, [], $invoice->fresh()->load('lines')->toArray(), $companyId);

        return redirect()
            ->route('sales.invoices.show', $invoice)
            ->with('success', 'Invoice draft saved.');
    }

    public function show(SalesInvoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        $invoice->load([
            'customer:id,name,code,email,phone,address',
            'lines.product:id,sku,name',
            'lines.taxRate:id,name,rate_percent',
            'receipts' => fn ($q) => $q->orderByDesc('receipt_date'),
        ]);

        $journal = $invoice->journal()->first();

        return Inertia::render('Sales/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'reference' => $invoice->reference,
                'notes' => $invoice->notes,
                'status' => $invoice->status,
                'paid_state' => $invoice->paidState(),
                'overdue' => $invoice->isOverdue(),
                'subtotal' => (float) $invoice->subtotal,
                'discount_amount' => (float) $invoice->discount_amount,
                'tax_amount' => (float) $invoice->tax_amount,
                'total' => (float) $invoice->total,
                'amount_paid' => (float) $invoice->amount_paid,
                'balance_due' => round($invoice->balanceDue(), 4),
                'posted_at' => $invoice->posted_at?->toDateTimeString(),
                'customer' => $invoice->customer,
                'lines' => $invoice->lines->map(fn (SalesInvoiceLine $line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product?->name ?? $line->description,
                    'product_sku' => $line->product?->sku,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'discount_amount' => (float) $line->discount_amount,
                    'tax_rate_name' => $line->taxRate?->name,
                    'tax_rate_percent' => (float) $line->tax_rate_percent,
                    'tax_amount' => (float) $line->tax_amount,
                    'line_total' => (float) $line->line_total,
                ]),
                'payments' => $invoice->receipts->map(fn ($receipt) => [
                    'id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_date' => $receipt->receipt_date->toDateString(),
                    'reference' => $receipt->reference,
                    'memo' => $receipt->memo,
                    'account_name' => $receipt->account?->name,
                    'amount' => (float) $receipt->pivot->amount,
                    'journal_id' => $receipt->journal?->id,
                    'journal_no' => $receipt->journal?->journal_no,
                ]),
                'journal_id' => $journal?->id,
                'journal_no' => $journal?->journal_no,
            ],
            'accounts' => $this->paymentAccountOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function edit(SalesInvoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        abort_if(! $invoice->isDraft(), 422, 'Only draft invoices can be edited.');

        $invoice->load('lines');

        return Inertia::render('Sales/Invoices/Edit', [
            'invoice' => [
                'id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'reference' => $invoice->reference,
                'notes' => $invoice->notes,
            ],
            'lines' => $invoice->lines->map(fn (SalesInvoiceLine $line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount_amount' => (float) $line->discount_amount,
                'tax_rate_id' => $line->tax_rate_id,
            ]),
            'customers' => $this->customerOptions(),
            'products' => $this->productOptions(),
            'taxRates' => $this->taxRateOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function update(SalesInvoiceRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        abort_if(! $invoice->isDraft(), 422, 'Only draft invoices can be updated.');

        $old = $invoice->load('lines')->toArray();
        $data = $request->validated();
        $payload = $this->buildTotals($data);

        $invoice->update([
            'customer_id' => $data['customer_id'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $payload['subtotal'],
            'discount_amount' => $payload['discount_amount'],
            'tax_amount' => $payload['tax_amount'],
            'total' => $payload['total'],
            'updated_by' => Auth::id(),
        ]);

        $invoice->lines()->delete();
        $this->saveLines($invoice, $data['lines']);

        AuditLogger::log('sales_invoice', 'update', null, $invoice->id, $old, $invoice->fresh()->load('lines')->toArray(), $invoice->company_id);

        return redirect()
            ->route('sales.invoices.show', $invoice)
            ->with('success', 'Invoice draft updated.');
    }

    public function post(SalesInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        try {
            $invoice = $this->service->postInvoice($invoice);

            return redirect()
                ->route('sales.invoices.show', $invoice)
                ->with('success', "Invoice {$invoice->invoice_no} posted.");
        } catch (SalesPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pay(ReceiptRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        try {
            $receipt = $this->service->recordPayment($invoice, $request->validated());

            AuditLogger::log('receipt', 'create', null, $receipt->id, [], $receipt->toArray(), $invoice->company_id);

            return redirect()
                ->route('sales.invoices.show', $invoice)
                ->with('success', "Receipt {$receipt->receipt_no} posted — payment recorded.");
        } catch (SalesPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(SalesInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        abort_if(! $invoice->isDraft(), 422, 'Only draft invoices can be deleted.');

        $companyId = $invoice->company_id;
        $invoice->delete();

        AuditLogger::log('sales_invoice', 'delete', null, $invoice->id, [], [], $companyId);

        return redirect()
            ->route('sales.invoices.index')
            ->with('success', 'Draft invoice deleted.');
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function authorizeInvoice(SalesInvoice $invoice): void
    {
        abort_if($invoice->company_id !== current_company_id(), 403, 'This invoice belongs to a different company.');
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

    private function saveLines(SalesInvoice $invoice, array $lines): void
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
                'unit_price' => (float) $line['unit_price'],
                'discount_amount' => $computed['discount_amount'],
                'tax_rate_id' => $taxRateId ?: null,
                'tax_rate_percent' => $computed['tax_percent'],
                'tax_amount' => $computed['tax_amount'],
                'line_total' => $computed['line_total'],
            ];
        }

        $invoice->lines()->createMany($rows);
    }

    /** Recompute a line's money from the request so totals never trust client math. */
    private function lineComputed(array $line): array
    {
        $quantity = (float) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $discountAmount = round((float) ($line['discount_amount'] ?? 0), 4);

        $lineTotal = round(max($quantity * $unitPrice - $discountAmount, 0), 4);

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

    private function customerOptions(): array
    {
        $companyId = current_company_id();

        $outstanding = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->select('customer_id', DB::raw('SUM(total) - SUM(amount_paid) as balance'))
            ->groupBy('customer_id')
            ->pluck('balance', 'customer_id');

        return Customer::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'credit_limit', 'payment_terms_days'])
            ->map(fn (Customer $customer) => [
                'value' => $customer->id,
                'label' => $customer->code ? "{$customer->code} — {$customer->name}" : $customer->name,
                'credit_limit' => (float) $customer->credit_limit,
                'payment_terms_days' => (int) $customer->payment_terms_days,
                'outstanding' => round((float) ($outstanding[$customer->id] ?? 0), 4),
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
                'sales_price' => (float) $product->sales_price,
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