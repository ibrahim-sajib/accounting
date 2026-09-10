<?php

namespace App\Domain\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string|bool|int'],
        ];
    }
}