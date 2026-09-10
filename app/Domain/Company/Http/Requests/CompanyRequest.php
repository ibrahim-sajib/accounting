<?php

namespace App\Domain\Company\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'tax_registration_no' => ['nullable', 'string', 'max:100'],
            'vat_registration_no' => ['nullable', 'string', 'max:100'],
            'accounting_basis' => ['required', 'in:accrual,cash'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'fiscal_year_start' => ['nullable', 'date'],
            'fiscal_year_end' => ['nullable', 'date'],
        ];
    }
}