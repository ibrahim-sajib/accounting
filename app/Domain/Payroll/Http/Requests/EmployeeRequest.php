<?php

namespace App\Domain\Payroll\Http\Requests;

use App\Domain\Payroll\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');
        $employee = $this->route('employee');
        $employeeId = $employee instanceof Employee ? $employee->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->ignore($employeeId, 'id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')
                ->where('company_id', $companyId)],
            'designation_id' => ['required', 'integer', Rule::exists('designations', 'id')
                ->where('company_id', $companyId)],
            'join_date' => ['required', 'date'],
            'is_active' => ['boolean'],
            // Salary structure fields
            'basic' => ['required', 'numeric', 'min:0'],
            'house_rent_allowance' => ['required', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'travel_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'income_tax_deduction' => ['nullable', 'numeric', 'min:0'],
            'provident_fund_deduction' => ['nullable', 'numeric', 'min:0'],
            'other_deduction' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'house_rent_allowance', 'medical_allowance', 'travel_allowance', 'other_allowance',
            'income_tax_deduction', 'provident_fund_deduction', 'other_deduction',
        ] as $field) {
            if (array_key_exists($field, $this->all()) && ($this->input($field) === null || $this->input($field) === '')) {
                $this->merge([$field => '0']);
            }
        }

        if (is_bool($this->input('is_active')) || $this->input('is_active') === null) {
            $this->merge(['is_active' => $this->input('is_active', true)]);
        }
    }
}