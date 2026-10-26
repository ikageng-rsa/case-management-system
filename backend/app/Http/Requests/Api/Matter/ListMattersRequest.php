<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Matter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMattersRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['open', 'mine', 'unassigned', 'closed'])],
            'sort' => ['nullable', Rule::in(['next_date', 'opened', 'reference'])],
            'matter_type_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Which matters: open, mine, unassigned or closed. Open unless one of the others. */
    public function filter(): string
    {
        return $this->validated('filter') ?? 'open';
    }

    /** How to order them: by next diary date, date opened or reference. Next date unless one of the others. */
    public function sort(): string
    {
        return $this->validated('sort') ?? 'next_date';
    }

    /** The matter type to narrow to; null lists every type. */
    public function matterTypeId(): ?int
    {
        return $this->integer('matter_type_id') ?: null;
    }

    /** A reference, title, client or party, in loose words, as on the web's search. */
    public function search(): ?string
    {
        return $this->filled('q') ? trim((string) $this->validated('q')) : null;
    }
}
