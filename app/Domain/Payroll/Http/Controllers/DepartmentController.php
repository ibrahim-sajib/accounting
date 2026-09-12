<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payroll\Exceptions\PayrollPostingException;
use App\Domain\Payroll\Http\Requests\DepartmentRequest;
use App\Domain\Payroll\Models\Department;
use App\Domain\Payroll\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('payroll.employees');
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        $department = Department::query()->create([
            'company_id' => $companyId,
            'name' => trim($request->input('name')),
            'is_active' => (bool) $request->input('is_active', true),
        ]);

        AuditLogger::log('payroll', 'create', 'department', $department->id, [], $department->toArray(), $companyId);

        return back()->with('success', 'Department added.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorizeCompany($department);

        $old = $department->toArray();
        $department->update([
            'name' => trim($request->input('name')),
            'is_active' => (bool) $request->input('is_active', $department->is_active),
        ]);

        AuditLogger::log('payroll', 'update', 'department', $department->id, $old, $department->toArray(), $department->company_id);

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorizeCompany($department);

        if (Employee::query()->where('department_id', $department->id)->exists()) {
            $department->update(['is_active' => false]);

            AuditLogger::log('payroll', 'delete', 'department', $department->id, [], ['deactivated' => true], $department->company_id);

            return back()->with('info', 'This department is assigned to employees — deactivated instead of deleted.');
        }

        AuditLogger::log('payroll', 'delete', 'department', $department->id, [], [], $department->company_id);
        $department->delete();

        return back()->with('success', 'Department deleted.');
    }

    protected function authorizeCompany(Department $department): void
    {
        abort_if((int) $department->company_id !== (int) session('active_company_id'), 404);
    }
}