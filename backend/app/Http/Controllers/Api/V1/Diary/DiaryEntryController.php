<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Diary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Diary\ListDiaryEntriesRequest;
use App\Http\Requests\Api\Diary\StoreDiaryEntryRequest;
use App\Http\Requests\Api\Diary\UpdateDiaryEntryRequest;
use App\Http\Resources\Diary\DiaryEntryResource;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Queries\Matters\MatterDiary;
use App\Services\Diary\DiariseNextAction;
use App\Services\Diary\ResolveReminderLead;
use App\Services\Diary\UpdateDiaryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DiaryEntryController extends Controller
{
    /**
     * The matter's diary for one view, soonest due first, with how many entries
     * each view holds. A matter's diary is short, so it comes in one page.
     */
    public function index(ListDiaryEntriesRequest $request, Matter $matter, MatterDiary $diary): AnonymousResourceCollection
    {
        abort_unless(Gate::allows('view', $matter), 404);

        $diary = $diary->for($matter, $request->user(), $request->show());

        return DiaryEntryResource::collection($diary['entries'])
            ->additional(['meta' => ['counts' => $diary['diaryCounts']]]);
    }

    /**
     * Diarise the next thing to do, for the user or a colleague. Without a
     * reminder chosen, it follows how the user usually likes to be reminded.
     */
    public function store(StoreDiaryEntryRequest $request, Matter $matter, DiariseNextAction $diarise, ResolveReminderLead $resolveLead): JsonResponse
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('work', $matter);

        $entry = $diarise->diarise(
            matter: $matter,
            assignee: $request->assignee(),
            body: $request->validated('body'),
            dueAt: $request->date('due_at'),
            diarisedBy: $request->user(),
            remindBeforeMinutes: $request->choseReminder() ? $request->reminderMinutes() : $resolveLead->for($request->user()),
        );

        return (new DiaryEntryResource($entry->load('assignee')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Change what is to be done, by when, for whom, and the narration it is
     * linked to. Anyone carrying the matter may, while the entry is still to do;
     * once done it is a record and stays as it was.
     */
    public function update(UpdateDiaryEntryRequest $request, Matter $matter, DiaryEntry $diaryEntry, UpdateDiaryEntry $updateEntry): DiaryEntryResource
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('work', $matter);
        abort_if($diaryEntry->isComplete(), 409, 'This entry is done, so it can no longer be changed.');

        $updateEntry->update(
            entry: $diaryEntry,
            body: $request->validated('body'),
            dueAt: $request->date('due_at'),
            narration: $request->narration(),
            assignee: $request->assignee(),
            editedBy: $request->user(),
        );

        return new DiaryEntryResource($diaryEntry->load(['assignee', 'completedBy']));
    }
}
