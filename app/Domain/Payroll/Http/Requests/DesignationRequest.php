<?php

namespace App\Domain\Payroll\Http\Requests;

use App\Domain\Payroll\Models\Designation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');
        $designation = $this->route('designation');
        $designationId = $designation instanceof Designation ? $designation->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('designations', 'name')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->ignore($designationId, 'id')],
            'is_active' => ['boolean'],
        ];
    }
}