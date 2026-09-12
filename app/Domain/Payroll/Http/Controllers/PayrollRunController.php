<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Payroll\Exceptions\PayrollPostingException;
use App\Domain\Payroll\Http\Requests\PayrollRunLineRequest;
use App\Domain\Payroll\Http\Requests\ProcessPayrollRequest;
use App\Domain\Payroll\Http\Requests\SalaryPaymentRequest;
use App\Domain\Payroll\Models\Employee;
use App\Domain\Payroll\Models\PayrollRun;
use App\Domain\Payroll\Models\PayrollRunLine;
use App\Domain\Payroll\Services\PayrollService;
use App\Http\Controllers\Controller;
use App\Support\Enums\SalaryPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    public function __construct(protected PayrollService $payrollService) {}

    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $query = PayrollRun::query()->with('period')
            ->where('company_id', $companyId);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $runs = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return Inertia::render('Payroll/Index', [
            'runs' => $runs->through(fn (PayrollRun $run) => [
                'id' => $run->id,
                'run_no' => $run->run_no,
                'period' => $run->period?->name,
                'period_id' => $run->period_id,
                'run_date' => $run->run_date?->toDateString(),
                'status' => $run->status,
                'total_gross' => $run->total_gross,
                'total_deductions' => $run->total_deductions,
                'total_net' => $run->total_net,
                'employee_count' => $run->lines()->count(),
                'paid_state' => $run->isPosted() ? $run->paidState() : null,
                'paid_total' => $run->paidTotal(),
                'remaining' => $run->remainingPayable(),
                'journal_no' => $run->journal?->journal_no,
            ]),
            'periods' => $this->periodOptions($companyId),
            'filters' => $request->only(['status']),
        ]);
    }

    public function store(ProcessPayrollRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        try {
            $run = $this->payrollService->processPayroll($request->validated(), $companyId);
        } catch (PayrollPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('payroll', 'process', 'payroll_run', $run->id, [], $run->toArray(), $companyId);

        return redirect()->route('payroll.runs.show', $run)
            ->with('success', 'Draft payroll run created — review lines then post.');
    }

    public function show(PayrollRun $run): Response
    {
        $this->authorizeCompany($run);

        $companyId = (int) session('active_company_id');
        $period = $run->period;

        return Inertia::render('Payroll/RunShow', [
            'run' => [
                'id' => $run->id,
                'run_no' => $run->run_no,
                'period' => $period?->name,
                'period_id' => $run->period_id,
                'run_date' => $run->run_date?->toDateString(),
                'status' => $run->status,
                'total_gross' => $run->total_gross,
                'total_deductions' => $run->total_deductions,
                'total_net' => $run->total_net,
                'journal_id' => $run->journal_id,
                'journal_no' => $run->journal?->journal_no,
                'posted_at' => $run->posted_at?->toISOString(),
                'employee_count' => $run->lines()->count(),
                'paid_total' => $run->paidTotal(),
                'remaining' => $run->remainingPayable(),
                'paid_state' => $run->isPosted() ? $run->paidState() : null,
            ],
            'lines' => $run->lines()->with('employee')->get()
                ->map(fn (PayrollRunLine $line) => [
                    'id' => $line->id,
                    'employee_id' => $line->employee_id,
                    'employee_name' => $line->employee?->name,
                    'department' => $line->employee?->department?->name,
                    'designation' => $line->employee?->designation?->name,
                    'gross_pay' => $line->gross_pay,
                    'allowances_total' => $line->allowances_total,
                    'deductions_total' => $line->deductions_total,
                    'net_pay' => $line->net_pay,
                ]),
            'payments' => $run->payments()
                ->with('journal')
                ->orderByDesc('payment_date')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'payment_no' => $p->payment_no,
                    'payment_method' => $p->payment_method,
                    'amount' => $p->amount,
                    'payment_date' => $p->payment_date?->toDateString(),
                    'journal_no' => $p->journal?->journal_no,
                ]),
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => $a->name])
                ->values()
                ->all(),
            'bankAccounts' => BankAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'bank_name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => ($a->account_name.' ('.$a->bank_name.')')])
                ->values()
                ->all(),
            'paymentMethods' => collect(SalaryPaymentMethod::cases())
                ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
                ->values()
                ->all(),
        ]);
    }

    public function updateLine(PayrollRun $run, PayrollRunLine $line, PayrollRunLineRequest $request): RedirectResponse
    {
        abort_if((int) $line->company_id !== (int) session('active_company_id'), 404);

        try {
            $this->payrollService->updateRunLine($line, $request->validated(), (int) session('active_company_id'));
        } catch (PayrollPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Line updated. Totals recomputed.');
    }

    public function post(PayrollRun $run): RedirectResponse
    {
        $this->authorizeCompany($run);

        try {
            $result = $this->payrollService->postRun($run, (int) session('active_company_id'));
        } catch (PayrollPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('payroll', 'post', 'payroll_run', $run->id, [], $run->fresh()->toArray(), $run->company_id);

        return redirect()->route('payroll.runs.show', $run)
            ->with('success', 'Payroll run posted ('.$result['journal']->journal_no.').');
    }

    public function destroy(PayrollRun $run): RedirectResponse
    {
        $this->authorizeCompany($run);

        try {
            $this->payrollService->destroyRun($run, (int) session('active_company_id'));
        } catch (PayrollPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('payroll', 'delete', 'payroll_run', $run->id, [], [], $run->company_id);

        return redirect()->route('payroll.index')->with('success', 'Draft payroll run deleted.');
    }

    public function storePayment(PayrollRun $run, SalaryPaymentRequest $request): RedirectResponse
    {
        $this->authorizeCompany($run);

        try {
            $result = $this->payrollService->storeSalaryPayment($run, $request->validated(), (int) session('active_company_id'));
        } catch (PayrollPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('payroll', 'create', 'salary_payment', $result['payment']->id, [], $result['payment']->toArray(), $run->company_id);

        return back()->with('success', 'Salary payment recorded ('.$result['journal']->journal_no.').');
    }

    protected function authorizeCompany(PayrollRun $run): void
    {
        abort_if((int) $run->company_id !== (int) session('active_company_id'), 404);
    }

    protected function periodOptions(int $companyId): array
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'open')
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date'])
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])
            ->values()
            ->all();
    }
}