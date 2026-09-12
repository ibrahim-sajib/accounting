<?php

namespace App\Domain\Document\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to attach.',
            'file.mimes' => 'Only documents and images up to 10MB are allowed.',
            'file.max' => 'The file must not be larger than 10MB.',
        ];
    }
}