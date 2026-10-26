<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Narration;

use Illuminate\Foundation\Http\FormRequest;

class ListNarrationsRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** `mine` or an activity code; null lists everything. */
    public function filter(): ?string
    {
        return $this->filled('filter') ? (string) $this->validated('filter') : null;
    }
}
