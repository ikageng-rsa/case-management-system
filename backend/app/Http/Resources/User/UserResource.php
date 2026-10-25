<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A person of the firm. Others are shown by name and title only; the address,
 * roles and permissions are given to the person themselves.
 *
 * @mixin User
 */
class UserResource extends JsonApiResource
{
    private bool $asSelf = false;

    /** Show the person in full though they are not yet signed in, as when signing in hands them their token. */
    public function asSelf(): static
    {
        $this->asSelf = true;

        return $this;
    }

    public function toType(Request $request): string
    {
        return 'users';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        $isSelf = $this->asSelf || $this->resource->is($request->user());

        return [
            'name' => $this->name,
            'job_title' => $this->job_title,
            'email' => $this->when($isSelf, fn () => $this->email),
            'roles' => $this->when($isSelf, fn () => $this->getRoleNames()->values()),
            // What the app may offer. The server checks again on every request.
            'permissions' => $this->when($isSelf, fn () => $this->getAllPermissions()->pluck('name')->sort()->values()),
        ];
    }
}
