<?php

namespace App\Domain\Receivables\Http\Controllers;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Party\Models\Customer;
use App\Domain\Receivables\Exceptions\ReceivablePostingException;
use App\Domain\Receivables\Http\Requests\AdvanceApplicationRequest;
use App\Domain\Receivables\Http\Requests\AdvanceReceiptRequest;
use App\Domain\Receivables\Http\Requests\ReceivablePaymentRequest;
use App\Domain\Receivables\Http\Requests\WriteOffRequest;
use App\Domain\Receivables\Services\ReceivableService;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\SalesInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController
{
    public function __construct(
        protected ReceivableService $service
    ) {}

    public function index(): Response
    {
        $companyId = current_company_id();

        return Inertia::render('Receivables/Index', [
            'dashboard' => $this->service->dashboard($companyId),
        ]);
    }

    public function outstanding(Request $request): Response
    {
        $companyId = current_company_id();

        $rows = $this->service->receivableRows($companyId);

        $search = $request->input('search');
        $customerId = $request->input('customer_id');

        if ($search) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['invoice_no'].' '.($row['customer']['name'] ?? '')), strtolower($search)));
        }

        if ($customerId) {
            $rows = $rows->where('customer_id', (int) $customerId)->values();
        }

        return Inertia::render('Receivables/Outstanding', [
            'rows' => $rows->values()->all(),
            'customers' => $this->customerOptions(),
            'filters' => $request->only(['search', 'customer_id']),
            'totals' => [
                'balance_due' => round($rows->sum('balance_due'), 4),
                'overdue' => round($rows->where('days_overdue', '>', 0)->sum('balance_due'), 4),
                'count' => $rows->count(),
            ],
        ]);
    }

    public function aging(): Response
    {
        return Inertia::render('Receivables/Aging', [
            'rows' => $this->service->agingRows(current_company_id()),
        ]);
    }

    public function createPayment(Request $request): Response
    {
        $companyId = current_company_id();
        $customerId = $request->integer('customer_id') ?: null;

        $outstanding = $customerId
            ? $this->service->receivableRows($companyId)->where('customer_id', $customerId)->values()->all()
            : [];

        return Inertia::render('Receivables/RecordPayment', [
            'customers' => $this->customerOptions(),
            'accounts' => $this->paymentAccountOptions(),
            'selectedCustomerId' => $customerId,
            'outstandingInvoices' => $outstanding,
            'today' => now()->toDateString(),
        ]);
    }

    public function storePayment(ReceivablePaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = Customer::query()
            ->where('company_id', current_company_id())
            ->findOrFail($data['customer_id']);

        try {
            $receipt = $this->service->recordPayment($customer, $data);

            AuditLogger::log('receipt', 'create', null, $receipt->id, [], $receipt->toArray(), $customer->company_id);

            return redirect()
                ->route('outstanding.index')
                ->with('success', "Receipt {$receipt->receipt_no} posted — payment allocated across "
                    .count($data['allocations']).' invoice(s).');
        } catch (ReceivablePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function createAdvance(Request $request): Response
    {
        return Inertia::render('Receivables/Advances/Create', [
            'customers' => $this->customerOptions(),
            'accounts' => $this->paymentAccountOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function storeAdvance(AdvanceReceiptRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = Customer::query()
            ->where('company_id', current_company_id())
            ->findOrFail($data['customer_id']);

        try {
            $receipt = $this->service->recordAdvance($customer, $data);

            AuditLogger::log('receipt', 'create', null, $receipt->id, [], $receipt->toArray(), $customer->company_id);

            return redirect()
                ->route('advances.show', $receipt)
                ->with('success', "Advance receipt {$receipt->receipt_no} posted.");
        } catch (ReceivablePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function advances(Request $request): Response
    {
        $companyId = current_company_id();

        $advances = Receipt::query()
            ->with('customer:id,name,code')
            ->where('company_id', $companyId)
            ->where('type', 'advance')
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Receipt $receipt) {
                return [
                    'id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_date' => $receipt->receipt_date?->toDateString(),
                    'customer' => $receipt->customer ? [
                        'id' => $receipt->customer->id,
                        'code' => $receipt->customer->code,
                        'name' => $receipt->customer->name,
                    ] : null,
                    'amount' => (float) $receipt->amount,
                    'applied_amount' => round($receipt->appliedAmount(), 4),
                    'balance' => round($receipt->advanceBalance(), 4),
                ];
            });

        return Inertia::render('Receivables/Advances/Index', [
            'advances' => $advances,
            'customers' => $this->customerOptions(),
            'accounts' => $this->paymentAccountOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function showAdvance(Receipt $receipt): Response
    {
        abort_unless($receipt->isAdvance(), 404);

        abort_unless($receipt->company_id === current_company_id(), 403);

        $receipt->load(['customer:id,name,code,email,phone,address', 'account:id,code,name', 'allocations.invoice:id,invoice_no,invoice_date,total,amount_paid,write_off_amount']);

        $allocatedInvoiceIds = $receipt->allocations->pluck('sales_invoice_id')->all();

        $outstanding = $this->service->receivableRows($receipt->company_id)
            ->where('customer_id', $receipt->customer_id)
            ->whereNotIn('id', $allocatedInvoiceIds)
            ->values()
            ->all();

        return Inertia::render('Receivables/Advances/Show', [
            'advance' => [
                'id' => $receipt->id,
                'receipt_no' => $receipt->receipt_no,
                'receipt_date' => $receipt->receipt_date->toDateString(),
                'reference' => $receipt->reference,
                'memo' => $receipt->memo,
                'status' => $receipt->status,
                'amount' => (float) $receipt->amount,
                'applied_amount' => round($receipt->appliedAmount(), 4),
                'balance' => round($receipt->advanceBalance(), 4),
                'customer' => $receipt->customer,
                'account' => $receipt->account ? [
                    'id' => $receipt->account->id,
                    'code' => $receipt->account->code,
                    'name' => $receipt->account->name,
                ] : null,
                'allocations' => $receipt->allocations->map(fn ($allocation) => [
                    'invoice_no' => $allocation->invoice?->invoice_no,
                    'invoice_date' => $allocation->invoice?->invoice_date?->toDateString(),
                    'amount' => (float) $allocation->amount,
                    'applied_state' => $allocation->invoice ? $allocation->invoice->paidState() : null,
                ]),
                'journal_id' => $receipt->journal?->id,
                'journal_no' => $receipt->journal?->journal_no,
            ],
            'outstandingInvoices' => $outstanding,
            'today' => now()->toDateString(),
        ]);
    }

    public function storeAdvanceApplication(AdvanceApplicationRequest $request, Receipt $receipt): RedirectResponse
    {
        abort_unless($receipt->isAdvance(), 404);
        abort_unless($receipt->company_id === current_company_id(), 403);

        try {
            $receipt = $this->service->applyAdvance($receipt, $request->validated()['allocations']);

            AuditLogger::log('receivable', 'apply_advance', null, $receipt->id, [], $receipt->fresh()->toArray(), $receipt->company_id);

            return redirect()
                ->route('advances.show', $receipt)
                ->with('success', "Advance {$receipt->receipt_no} applied to invoices.");
        } catch (ReceivablePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function writeOff(WriteOffRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->company_id === current_company_id(), 403);

        try {
            $invoice = $this->service->writeOff($invoice, $request->validated());

            AuditLogger::log('receivable', 'write_off', null, $invoice->id, [], $invoice->toArray(), $invoice->company_id);

            return redirect()
                ->route('sales.invoices.show', $invoice)
                ->with('success', "Write-off of {$request->input('amount')} recorded against invoice {$invoice->invoice_no}.");
        } catch (ReceivablePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function customerOptions(): array
    {
        $companyId = current_company_id();

        $outstanding = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->select('customer_id', \Illuminate\Support\Facades\DB::raw('SUM(total) - SUM(amount_paid) - SUM(write_off_amount) as balance'))
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