<?php

namespace App\Domain\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $entries = $this->input('entries') ?? [];

        $entries = collect($entries)
            ->map(function (array $entry) {
                $entry['debit'] = is_numeric($entry['debit'] ?? null) ? (string) $entry['debit'] : '0';
                $entry['credit'] = is_numeric($entry['credit'] ?? null) ? (string) $entry['credit'] : '0';

                return $entry;
            })
            ->values()
            ->all();

        $this->merge(['entries' => $entries]);
    }

    public function rules(): array
    {
        return [
            'entries' => ['required', 'array'],
            'entries.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'entries.*.debit' => ['required', 'numeric', 'min:0'],
            'entries.*.credit' => ['required', 'numeric', 'min:0'],
        ];
    }
}