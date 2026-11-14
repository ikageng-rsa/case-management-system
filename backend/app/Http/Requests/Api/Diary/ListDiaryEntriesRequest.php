<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Diary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDiaryEntriesRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'show' => ['nullable', Rule::in(['pending', 'completed', 'overdue', 'mine'])],
        ];
    }

    /** Which entries: pending, completed, overdue, or the user's own pending. Pending unless one of the others. */
    public function show(): string
    {
        return $this->validated('show') ?? 'pending';
    }
}
