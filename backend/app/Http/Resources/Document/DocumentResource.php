<?php

declare(strict_types=1);

namespace App\Http\Resources\Document;

use App\Http\Resources\ApiResource;
use App\Models\Document\Media;
use Illuminate\Http\Request;

/**
 * A document filed on a matter. Where it is stored never leaves the server: the
 * file is fetched through the API, with the user's token.
 *
 * @mixin Media
 */
class DocumentResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'documents';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'file_name' => $this->file_name,
            'kind' => $this->getCustomProperty('kind'),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
