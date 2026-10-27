<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Matter;

use App\Http\Controllers\Controller;
use App\Http\Resources\Matter\MatterPartyResource;
use App\Models\Matter\Matter;
use App\Queries\Matters\MatterParties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MatterPartyController extends Controller
{
    /**
     * Everyone in the matter besides the client, in the order they were added. A
     * matter has few, so they come in one page. They are read here, never changed.
     */
    public function index(Request $request, Matter $matter, MatterParties $parties): AnonymousResourceCollection
    {
        abort_unless(Gate::allows('view', $matter), 404);

        return MatterPartyResource::collection($parties->for($matter, $request->user())['matterParties']);
    }
}
