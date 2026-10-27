<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Narration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Narration\ListNarrationsRequest;
use App\Http\Requests\Api\Narration\StoreNarrationRequest;
use App\Http\Resources\Narration\NarrationResource;
use App\Models\Matter\Matter;
use App\Queries\Matters\MatterLedger;
use App\Services\Narrations\RecordNarration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

class NarrationController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * The matter's ledger, newest first, a page at a time, with the counts behind
     * each filter so the app can offer them.
     */
    public function index(ListNarrationsRequest $request, Matter $matter, MatterLedger $ledger): AnonymousResourceCollection
    {
        abort_unless(Gate::allows('view', $matter), 404);

        $ledger = $ledger->for($matter, $request->user(), $request->filter());
        $page = Paginator::resolveCurrentPage();

        $narrations = new LengthAwarePaginator(
            $ledger['narrations']->forPage($page, self::PER_PAGE)->values(),
            $ledger['narrations']->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return NarrationResource::collection($narrations)->additional(['meta' => [
            'counts' => [
                'all' => $ledger['allCount'],
                'mine' => $ledger['mineCount'],
                'activities' => $ledger['filters'],
            ],
            'minutes_recorded' => $ledger['minutesRecorded'],
        ]]);
    }

    /** Log work on the matter. Only those carrying an open matter may. */
    public function store(StoreNarrationRequest $request, Matter $matter, RecordNarration $recordNarration): JsonResponse
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('work', $matter);

        $narration = $recordNarration->record(
            matter: $matter,
            activityType: $request->activityType(),
            author: $request->user(),
            body: $request->validated('body'),
            quantity: (float) $request->validated('quantity'),
            occurredAt: $request->date('occurred_at'),
        );

        return (new NarrationResource($narration->load(['activityType', 'author'])))
            ->response()
            ->setStatusCode(201);
    }
}
