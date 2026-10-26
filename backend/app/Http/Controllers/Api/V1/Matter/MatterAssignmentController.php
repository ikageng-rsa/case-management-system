<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Matter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Matter\AssignUserRequest;
use App\Http\Resources\Matter\MatterAssignmentResource;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Services\Matters\AssignUserToMatter;
use App\Services\Matters\CannotAssignToClosedMatter;
use App\Services\Matters\UnassignUserFromMatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/*
 * The team on a matter. Anyone who can see the matter sees who carries it;
 * changing the team needs the assign permission, as on the web.
 */
class MatterAssignmentController extends Controller
{
    /** The current team, longest on the matter first. A team is small, so it comes in one page. */
    public function index(Matter $matter): AnonymousResourceCollection
    {
        abort_unless(Gate::allows('view', $matter), 404);

        return MatterAssignmentResource::collection(
            $matter->assignments()->active()->with('user')->orderBy('assigned_at')->orderBy('id')->get(),
        );
    }

    /**
     * Put someone on the matter. Taking a seat held by one person at a time, as
     * Responsible, retires its holder. Asking again for a seat already held
     * changes nothing and answers with it.
     */
    public function store(AssignUserRequest $request, Matter $matter, AssignUserToMatter $assignUser): JsonResponse
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('assign', $matter);

        try {
            $assignment = $assignUser->assign($matter, $request->assignee(), $request->capacity());
        } catch (CannotAssignToClosedMatter $e) {
            abort(409, $e->getMessage());
        }

        return (new MatterAssignmentResource($assignment->load('user')))
            ->response()
            ->setStatusCode($assignment->wasRecentlyCreated ? 201 : 200);
    }

    /** Take someone off the matter. The assignment is kept as history, so it can only end once. */
    public function destroy(Matter $matter, MatterAssignment $assignment, UnassignUserFromMatter $unassignUser): Response
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('assign', $matter);
        abort_unless($assignment->isActive(), 404);

        $unassignUser->unassign($matter, $assignment->user, $assignment->capacity);

        return response()->noContent();
    }
}
