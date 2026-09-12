<?php

namespace App\Domain\Payroll\Http\Requests;

use App\Domain\Payroll\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->ignore($departmentId, 'id')],
            'is_active' => ['boolean'],
        ];
    }
}