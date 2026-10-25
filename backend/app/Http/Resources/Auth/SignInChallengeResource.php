<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A sign-in waiting for its emailed code. Its id is what the app sends back with the code.
 *
 * @property array{id: string, expires_in: int} $resource
 */
class SignInChallengeResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource['id'];
    }

    public function toType(Request $request): string
    {
        return 'sign-in-challenges';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'expires_in' => $this->resource['expires_in'],
        ];
    }
}
