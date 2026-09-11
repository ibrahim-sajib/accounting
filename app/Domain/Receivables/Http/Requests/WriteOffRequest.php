<?php

namespace App\Domain\Receivables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WriteOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! is_numeric($this->input('amount'))) {
            $this->merge(['amount' => '0']);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}