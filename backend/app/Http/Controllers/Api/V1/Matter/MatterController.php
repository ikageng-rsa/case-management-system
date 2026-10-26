<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Matter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Matter\ListMattersRequest;
use App\Http\Resources\Matter\MatterResource;
use App\Models\Matter\Matter;
use App\Queries\Matters\MatterList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MatterController extends Controller
{
    /** The matters the user may see, with how many fall under each filter for the app's tabs. */
    public function index(ListMattersRequest $request, MatterList $matterList): AnonymousResourceCollection
    {
        $list = $matterList->for(
            $request->user(),
            $request->filter(),
            $request->sort(),
            $request->matterTypeId(),
            $request->search(),
        );

        return MatterResource::collection($list['matters']->withQueryString())
            ->additional(['meta' => ['counts' => $list['counts']]]);
    }

    /** A matter the user may not see is not found, so the app cannot learn that one exists. */
    public function show(Request $request, Matter $matter): MatterResource
    {
        abort_unless(Gate::allows('view', $matter), 404);

        $matter->load([
            'client',
            'matterType',
            'court',
            'assignments' => fn ($query) => $query->active()->orderBy('assigned_at'),
            'assignments.user',
            'diaryEntries' => fn ($query) => $query->pending(),
        ]);

        $matter->recordAccess('read', ['ip' => $request->ip(), 'via' => 'api']);

        return (new MatterResource($matter))->withTeam();
    }
}
