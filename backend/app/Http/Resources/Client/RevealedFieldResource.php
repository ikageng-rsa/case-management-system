<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * One masked field of a client, read in full. Its id is the target it was asked for by.
 *
 * @property array{target: string, field: string, value: string} $resource
 */
class RevealedFieldResource extends ApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource['target'];
    }

    public function toType(Request $request): string
    {
        return 'revealed-fields';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'field' => $this->resource['field'],
            'value' => $this->resource['value'],
        ];
    }
}
