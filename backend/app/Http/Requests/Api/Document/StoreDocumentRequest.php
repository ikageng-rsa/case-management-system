<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Document;

use App\Enums\Document\DocumentKind;
use App\Http\Requests\Document\StoreDocumentRequest as WebStoreDocumentRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/** The web's rules for what may be filed, with a name the app may give the document. */
class StoreDocumentRequest extends WebStoreDocumentRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function kind(): DocumentKind
    {
        return DocumentKind::from($this->validated('kind'));
    }

    /** How the document is listed; null lists it by its file's name. */
    public function name(): ?string
    {
        return $this->filled('name') ? trim((string) $this->validated('name')) : null;
    }
}
