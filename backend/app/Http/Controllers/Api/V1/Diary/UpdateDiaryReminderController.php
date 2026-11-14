<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Diary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Diary\UpdateDiaryReminderRequest;
use App\Http\Resources\Diary\DiaryEntryResource;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use Illuminate\Support\Facades\Gate;

class UpdateDiaryReminderController extends Controller
{
    /**
     * The person an entry is for decides how early they are reminded of it,
     * whatever the person who set it chose. Once done, it reminds no one.
     */
    public function __invoke(UpdateDiaryReminderRequest $request, Matter $matter, DiaryEntry $diaryEntry): DiaryEntryResource
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('work', $matter);
        abort_unless($diaryEntry->assigned_to === $request->user()->getKey(), 403, 'Only the person this entry is for can change its reminder.');
        abort_if($diaryEntry->isComplete(), 409, 'This entry is done, so it has no reminder to change.');

        $diaryEntry->update(['remind_before_minutes' => $request->reminderMinutes()]);

        return new DiaryEntryResource($diaryEntry->load(['assignee', 'completedBy']));
    }
}
