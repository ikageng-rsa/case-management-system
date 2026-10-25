<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\Reference\ActivityTypeResource;
use App\Models\Reference\ActivityType;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

class ActivityTypeController extends Controller
{
    /** What work can be narrated as. Retired types are left out. */
    public function index(): AnonymousResourceCollection
    {
        return ActivityTypeResource::collection(ActivityType::query()->orderBy('name')->get());
    }
}
