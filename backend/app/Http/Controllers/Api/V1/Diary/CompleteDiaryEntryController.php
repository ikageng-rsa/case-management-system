<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Diary;

use App\Http\Controllers\Controller;
use App\Http\Resources\Diary\DiaryEntryResource;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Services\Diary\CompleteDiaryEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompleteDiaryEntryController extends Controller
{
    /**
     * Tick an entry off, recording who did. An entry is a reminder of a time, so
     * it cannot be done before that time. Ticking off one already done changes nothing.
     */
    public function __invoke(Request $request, Matter $matter, DiaryEntry $diaryEntry, CompleteDiaryEntry $completeEntry): DiaryEntryResource
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('work', $matter);

        if ($diaryEntry->isPending() && ! $diaryEntry->isDue()) {
            abort(409, 'This entry is not due until '.$diaryEntry->due_at->format('d M Y H:i').', so it cannot be marked done yet.');
        }

        $completeEntry->complete($diaryEntry, $request->user());

        return new DiaryEntryResource($diaryEntry->load(['assignee', 'completedBy']));
    }
}
