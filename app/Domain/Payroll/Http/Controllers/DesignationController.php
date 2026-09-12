<?php

namespace App\Domain\Payroll\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payroll\Http\Requests\DesignationRequest;
use App\Domain\Payroll\Models\Designation;
use App\Domain\Payroll\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DesignationController extends Controller
{
    public function store(DesignationRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        $designation = Designation::query()->create([
            'company_id' => $companyId,
            'name' => trim($request->input('name')),
            'is_active' => (bool) $request->input('is_active', true),
        ]);

        AuditLogger::log('payroll', 'create', 'designation', $designation->id, [], $designation->toArray(), $companyId);

        return back()->with('success', 'Designation added.');
    }

    public function update(DesignationRequest $request, Designation $designation): RedirectResponse
    {
        $this->authorizeCompany($designation);

        $old = $designation->toArray();
        $designation->update([
            'name' => trim($request->input('name')),
            'is_active' => (bool) $request->input('is_active', $designation->is_active),
        ]);

        AuditLogger::log('payroll', 'update', 'designation', $designation->id, $old, $designation->toArray(), $designation->company_id);

        return back()->with('success', 'Designation updated.');
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $this->authorizeCompany($designation);

        if (Employee::query()->where('designation_id', $designation->id)->exists()) {
            $designation->update(['is_active' => false]);

            AuditLogger::log('payroll', 'delete', 'designation', $designation->id, [], ['deactivated' => true], $designation->company_id);

            return back()->with('info', 'This designation is assigned to employees — deactivated instead of deleted.');
        }

        AuditLogger::log('payroll', 'delete', 'designation', $designation->id, [], [], $designation->company_id);
        $designation->delete();

        return back()->with('success', 'Designation deleted.');
    }

    protected function authorizeCompany(Designation $designation): void
    {
        abort_if((int) $designation->company_id !== (int) session('active_company_id'), 404);
    }
}