<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Audit\AuditEntryResource;
use App\Models\Matter\Matter;
use App\Queries\Matters\MatterAuditTrail;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MatterAuditEntryController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * Everything recorded against the matter and what hangs off it, newest
     * first. Only for those who may read the audit log and see the matter.
     */
    public function index(Matter $matter, MatterAuditTrail $auditTrail): AnonymousResourceCollection
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('viewAudit', $matter);

        return AuditEntryResource::collection($auditTrail->for($matter, self::PER_PAGE, 'page'));
    }
}
