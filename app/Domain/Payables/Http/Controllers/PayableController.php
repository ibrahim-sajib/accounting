<?php

namespace App\Domain\Payables\Http\Controllers;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Party\Models\Supplier;
use App\Domain\Payables\Exceptions\PayablePostingException;
use App\Domain\Payables\Http\Requests\MultiBillPaymentRequest;
use App\Domain\Payables\Http\Requests\SupplierAdvanceApplicationRequest;
use App\Domain\Payables\Http\Requests\SupplierAdvanceRequest;
use App\Domain\Payables\Services\PayableService;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayableController
{
    public function __construct(
        protected PayableService $service
    ) {}

    public function index(): Response
    {
        $companyId = current_company_id();

        return Inertia::render('Payables/Index', [
            'dashboard' => $this->service->dashboard($companyId),
        ]);
    }

    public function outstanding(Request $request): Response
    {
        $companyId = current_company_id();

        $rows = $this->service->payableRows($companyId);

        $search = $request->input('search');
        $supplierId = $request->input('supplier_id');

        if ($search) {
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['bill_no'].' '.($row['supplier']['name'] ?? '')), strtolower($search)));
        }

        if ($supplierId) {
            $rows = $rows->where('supplier_id', (int) $supplierId)->values();
        }

        return Inertia::render('Payables/Outstanding', [
            'rows' => $rows->values()->all(),
            'suppliers' => $this->supplierOptions(),
            'filters' => $request->only(['search', 'supplier_id']),
            'totals' => [
                'balance_due' => round($rows->sum('balance_due'), 4),
                'overdue' => round($rows->where('days_overdue', '>', 0)->sum('balance_due'), 4),
                'count' => $rows->count(),
            ],
        ]);
    }

    public function aging(): Response
    {
        return Inertia::render('Payables/Aging', [
            'rows' => $this->service->agingRows(current_company_id()),
        ]);
    }

    public function createPayment(Request $request): Response
    {
        $companyId = current_company_id();
        $supplierId = $request->integer('supplier_id') ?: null;

        $outstanding = $supplierId
            ? $this->service->payableRows($companyId)->where('supplier_id', $supplierId)->values()->all()
            : [];

        return Inertia::render('Payables/RecordPayment', [
            'suppliers' => $this->supplierOptions(),
            'accounts' => $this->paymentAccountOptions(),
            'selectedSupplierId' => $supplierId,
            'outstandingBills' => $outstanding,
            'today' => now()->toDateString(),
        ]);
    }

    public function storePayment(MultiBillPaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $supplier = Supplier::query()
            ->where('company_id', current_company_id())
            ->findOrFail($data['supplier_id']);

        try {
            $payment = $this->service->recordPayment($supplier, $data);

            AuditLogger::log('supplier_payment', 'create', null, $payment->id, [], $payment->toArray(), $supplier->company_id);

            return redirect()
                ->route('payable-outstanding.index')
                ->with('success', "Payment {$payment->payment_no} posted — payment allocated across "
                    .count($data['allocations']).' bill(s).');
        } catch (PayablePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeAdvance(SupplierAdvanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $supplier = Supplier::query()
            ->where('company_id', current_company_id())
            ->findOrFail($data['supplier_id']);

        try {
            $payment = $this->service->recordAdvance($supplier, $data);

            AuditLogger::log('supplier_payment', 'create', null, $payment->id, [], $payment->toArray(), $supplier->company_id);

            return redirect()
                ->route('supplier-advances.show', $payment)
                ->with('success', "Advance payment {$payment->payment_no} posted.");
        } catch (PayablePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function advances(Request $request): Response
    {
        $companyId = current_company_id();

        $advances = SupplierPayment::query()
            ->with('supplier:id,name,code')
            ->where('company_id', $companyId)
            ->where('type', 'advance')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (SupplierPayment $payment) {
                return [
                    'id' => $payment->id,
                    'payment_no' => $payment->payment_no,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'supplier' => $payment->supplier ? [
                        'id' => $payment->supplier->id,
                        'code' => $payment->supplier->code,
                        'name' => $payment->supplier->name,
                    ] : null,
                    'amount' => (float) $payment->amount,
                    'applied_amount' => round($payment->appliedAmount(), 4),
                    'balance' => round($payment->advanceBalance(), 4),
                ];
            });

        return Inertia::render('Payables/Advances/Index', [
            'advances' => $advances,
            'suppliers' => $this->supplierOptions(),
            'accounts' => $this->paymentAccountOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function showAdvance(SupplierPayment $payment): Response
    {
        abort_unless($payment->isAdvance(), 404);

        abort_unless($payment->company_id === current_company_id(), 403);

        $payment->load(['supplier:id,name,code,email,phone,address', 'account:id,code,name', 'allocations.bill:id,bill_no,bill_date,total,amount_paid']);

        $allocatedBillIds = $payment->allocations->pluck('purchase_bill_id')->all();

        $outstanding = $this->service->payableRows($payment->company_id)
            ->where('supplier_id', $payment->supplier_id)
            ->whereNotIn('id', $allocatedBillIds)
            ->values()
            ->all();

        return Inertia::render('Payables/Advances/Show', [
            'advance' => [
                'id' => $payment->id,
                'payment_no' => $payment->payment_no,
                'payment_date' => $payment->payment_date->toDateString(),
                'reference' => $payment->reference,
                'memo' => $payment->memo,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'applied_amount' => round($payment->appliedAmount(), 4),
                'balance' => round($payment->advanceBalance(), 4),
                'supplier' => $payment->supplier,
                'account' => $payment->account ? [
                    'id' => $payment->account->id,
                    'code' => $payment->account->code,
                    'name' => $payment->account->name,
                ] : null,
                'allocations' => $payment->allocations->map(fn ($allocation) => [
                    'bill_no' => $allocation->bill?->bill_no,
                    'bill_date' => $allocation->bill?->bill_date?->toDateString(),
                    'amount' => (float) $allocation->amount,
                    'applied_state' => $allocation->bill ? $allocation->bill->paidState() : null,
                ]),
                'journal_id' => $payment->journal?->id,
                'journal_no' => $payment->journal?->journal_no,
            ],
            'outstandingBills' => $outstanding,
            'today' => now()->toDateString(),
        ]);
    }

    public function storeAdvanceApplication(SupplierAdvanceApplicationRequest $request, SupplierPayment $payment): RedirectResponse
    {
        abort_unless($payment->isAdvance(), 404);
        abort_unless($payment->company_id === current_company_id(), 403);

        try {
            $payment = $this->service->applyAdvance($payment, $request->validated()['allocations']);

            AuditLogger::log('payable', 'apply_advance', null, $payment->id, [], $payment->fresh()->toArray(), $payment->company_id);

            return redirect()
                ->route('supplier-advances.show', $payment)
                ->with('success', "Advance {$payment->payment_no} applied to bills.");
        } catch (PayablePostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function supplierOptions(): array
    {
        $companyId = current_company_id();

        $outstanding = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->select('supplier_id', \Illuminate\Support\Facades\DB::raw('SUM(total) - SUM(amount_paid) as balance'))
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