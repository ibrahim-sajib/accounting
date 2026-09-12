<?php

namespace App\Domain\CashBank\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankStatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'statement_month' => ['required', 'regex:/^\d{4}-\d{1,2}(-\d{1,2})?$/'],
            'statement_file' => ['required', 'file', 'mimes:csv,txt'],
        ];
    }
}