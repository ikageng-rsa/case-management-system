<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Enums\Document\DocumentKind;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** @property DocumentKind $resource */
class DocumentKindResource extends ApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource->value;
    }

    public function toType(Request $request): string
    {
        return 'document-kinds';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => Str::headline($this->resource->value),
        ];
    }
}
