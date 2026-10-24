<?php

declare(strict_types=1);

namespace App\Http\Requests\Matter;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/*
 * The matters list is reached from links and bookmarks, so a query value it
 * doesn't recognise falls back to the default view instead of failing.
 */
class ListMattersRequest extends FormRequest
{
    private const FILTERS = ['all', 'mine', 'unassigned', 'closed'];

    private const SORTS = ['next_date', 'opened', 'reference'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /** Which matters to list: all open, mine, unassigned or closed. All unless one of the others. */
    public function filter(): string
    {
        $filter = $this->query('filter');

        return in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    /** How to order them: by next diary date, date opened or reference. Next date unless one of the others. */
    public function sort(): string
    {
        $sort = $this->query('sort');

        return in_array($sort, self::SORTS, true) ? $sort : 'next_date';
    }

    /** The matter type to narrow to; null lists every type. */
    public function matterTypeId(): ?int
    {
        return $this->integer('type') ?: null;
    }
}
