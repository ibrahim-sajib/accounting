<?php

namespace App\Domain\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $lines = $this->input('lines') ?? [];

        $lines = collect($lines)
            ->map(function (array $line) {
                $line['debit'] = $this->normalizeNumber($line['debit'] ?? null);
                $line['credit'] = $this->normalizeNumber($line['credit'] ?? null);

                return $line;
            })
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    public function rules(): array
    {
        return [
            'journal_date' => ['required', 'date'],
            'period_id' => ['required', 'integer', 'exists:accounting_periods,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ];
    }

    private function normalizeNumber(mixed $value): string
    {
        return is_numeric($value) ? (string) $value : '0';
    }
}