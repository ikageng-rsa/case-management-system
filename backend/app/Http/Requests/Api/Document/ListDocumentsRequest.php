<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Document;

use App\Enums\Document\DocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDocumentsRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(DocumentKind::class)],
        ];
    }

    /** The kind to narrow to; null lists every kind. */
    public function kind(): ?DocumentKind
    {
        return DocumentKind::tryFrom((string) $this->validated('kind'));
    }
}
