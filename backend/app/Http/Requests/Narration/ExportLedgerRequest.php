<?php

declare(strict_types=1);

namespace App\Http\Requests\Narration;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The export carries the same filter as the ledger it was downloaded from.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'string'],
        ];
    }

    /** `mine` or an activity code; null exports everything. */
    public function filter(): ?string
    {
        return $this->filled('filter') ? $this->validated('filter') : null;
    }
}
