<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payroll\Exceptions\PayrollPostingException;
use App\Domain\Payroll\Http\Requests\EmployeeRequest;
use App\Domain\Payroll\Models\Designation;
use App\Domain\Payroll\Models\Department;
use App\Domain\Payroll\Models\Employee;
use App\Domain\Payroll\Services\PayrollService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(protected PayrollService $payrollService) {}

    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $query = Employee::query()->with(['department', 'designation', 'salaryStructure'])
            ->where('company_id', $companyId);

        if ($request->input('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->input('designation_id')) {
            $query->where('designation_id', $request->input('designation_id'));
        }

        if ($request->boolean('inactive')) {
            $query->where('is_active', false);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Payroll/Employees', [
            'employees' => $employees->through(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'email' => $e->email,
                'phone' => $e->phone,
                'join_date' => $e->join_date?->toDateString(),
                'is_active' => $e->is_active,
                'department' => $e->department?->name,
                'designation' => $e->designation?->name,
                'gross_pay' => $e->salaryStructure?->grossPay(),
                'net_pay' => $e->salaryStructure?->netPay(),
            ]),
            'filters' => $request->only(['search', 'department_id', 'designation_id', 'inactive']),
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'is_active']),
            'designations' => Designation::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Payroll/EmployeesCreate', $this->formProps());
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        $employee = $this->payrollService->storeEmployee($request->validated(), $companyId);

        AuditLogger::log('payroll', 'create', 'employee', $employee->id, [], $employee->fresh()->toArray(), $employee->company_id);

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', 'Employee registered with salary structure.');
    }

    public function show(Employee $employee): Response
    {
        $this->authorizeCompany($employee);

        $employee->load(['department', 'designation', 'salaryStructure']);

        return Inertia::render('Payroll/EmployeeShow', [
            'employee' => $this->serialize($employee),
            'runs' => $employee->payrollRunLines()
                ->with('run.period')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($line) => [
                    'id' => $line->id,
                    'run_id' => $line->payroll_run_id,
                    'run_no' => $line->run?->run_no,
                    'period' => $line->run?->period?->name,
                    'run_status' => $line->run?->status,
                    'run_date' => $line->run?->run_date?->toDateString(),
                    'gross_pay' => $line->gross_pay,
                    'deductions_total' => $line->deductions_total,
                    'net_pay' => $line->net_pay,
                ]),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $this->authorizeCompany($employee);

        return Inertia::render('Payroll/EmployeesEdit', array_merge($this->formProps(), [
            'employee' => $this->serialize($employee->load(['department', 'designation', 'salaryStructure'])),
        ]));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeCompany($employee);

        $old = $employee->toArray();
        $this->payrollService->updateEmployee($employee, $request->validated());

        AuditLogger::log('payroll', 'update', 'employee', $employee->id, $old, $employee->fresh()->toArray(), $employee->company_id);

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorizeCompany($employee);

        try {
            $this->payrollService->destroyEmployee($employee);
        } catch (PayrollPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('payroll', 'delete', 'employee', $employee->id, [], [], $employee->company_id);

        return redirect()->route('payroll.employees')->with('success', 'Employee deleted.');
    }

    protected function authorizeCompany(Employee $employee): void
    {
        abort_if((int) $employee->company_id !== (int) session('active_company_id'), 404);
    }

    protected function serialize(Employee $employee): array
    {
        $dept = $employee->department;
        $designation = $employee->designation;

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'join_date' => $employee->join_date?->toDateString(),
            'is_active' => $employee->is_active,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'department' => $dept ? ['id' => $dept->id, 'name' => $dept->name] : null,
            'designation' => $designation ? ['id' => $designation->id, 'name' => $designation->name] : null,
            'salaryStructure' => $employee->salaryStructure,
        ];
    }

    public function formProps(): array
    {
        $companyId = (int) session('active_company_id');

        return [
            'departments' => $this->departmentOptions($companyId),
            'designations' => $this->designationOptions($companyId),
        ];
    }

    protected function departmentOptions(int $companyId): array
    {
        return Department::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])
            ->values()
            ->all();
    }

    protected function designationOptions(int $companyId): array
    {
        return Designation::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])
            ->values()
            ->all();
    }
}