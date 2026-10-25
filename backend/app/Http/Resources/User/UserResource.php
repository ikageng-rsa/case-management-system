<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin User */
class UserResource extends JsonApiResource
{
    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'job_title' => $this->job_title,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->values(),
            // What the app may offer. The server checks again on every request.
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }
}
