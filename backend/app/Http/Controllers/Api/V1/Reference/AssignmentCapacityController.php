<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reference;

use App\Enums\Matter\Assignment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Reference\AssignmentCapacityResource;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

class AssignmentCapacityController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AssignmentCapacityResource::collection(Assignment::cases());
    }
}
