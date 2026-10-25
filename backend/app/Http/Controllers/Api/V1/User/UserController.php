<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\ListUsersRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

class UserController extends Controller
{
    /** The people work can be put in the hands of: everyone still with the firm. */
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $search = $request->search();

        return UserResource::collection(
            User::query()
                ->when($search, fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        );
    }
}
