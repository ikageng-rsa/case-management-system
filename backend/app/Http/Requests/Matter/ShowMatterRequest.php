<?php

declare(strict_types=1);

namespace App\Http\Requests\Matter;

use App\Enums\Document\DocumentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/*
 * The matter page is reached from links and bookmarks, so a query value it
 * doesn't recognise falls back to the default view instead of failing.
 */
class ShowMatterRequest extends FormRequest
{
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

    /** The ledger filter is `mine` or an activity code; null shows everything. */
    public function ledgerFilter(): ?string
    {
        $filter = $this->query('filter');

        return is_string($filter) && $filter !== '' ? $filter : null;
    }

    /** The kind of document to narrow to; null, or a kind that doesn't exist, shows them all. */
    public function documentKind(): ?DocumentKind
    {
        $kind = $this->query('kind');

        return is_string($kind) ? DocumentKind::tryFrom($kind) : null;
    }

    /** Which diary entries to list: pending, completed, overdue or mine. Pending unless one of the others. */
    public function diaryShow(): string
    {
        $show = $this->query('show');

        return in_array($show, ['pending', 'completed', 'overdue', 'mine'], true) ? $show : 'pending';
    }
}
