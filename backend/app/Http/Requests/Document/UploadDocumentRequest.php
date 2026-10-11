<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use Illuminate\Validation\Rule;

/** Filing a document from the firm-wide list, where the matter is chosen rather than implied. */
class UploadDocumentRequest extends StoreDocumentRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'matter_id' => ['required', 'uuid', Rule::exists('matters', 'id')->withoutTrashed()],
        ];
    }
}
