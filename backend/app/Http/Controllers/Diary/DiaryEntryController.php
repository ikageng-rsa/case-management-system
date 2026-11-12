<?php

declare(strict_types=1);

namespace App\Http\Controllers\Diary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diary\StoreDiaryEntryRequest;
use App\Http\Requests\Diary\UpdateDiaryReminderRequest;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Services\Diary\CompleteDiaryEntry;
use App\Services\Diary\DiariseNextAction;
use App\Services\Diary\ResolveReminderLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DiaryEntryController extends Controller
{
    public function store(StoreDiaryEntryRequest $request, Matter $matter, DiariseNextAction $diarise, ResolveReminderLead $resolveLead): RedirectResponse
    {
        Gate::authorize('work', $matter);

        $diarise->diarise(
            matter: $matter,
            assignee: $request->assignee(),
            body: $request->validated('body'),
            dueAt: $request->date('due_at'),
            diarisedBy: $request->user(),
            // What was chosen on the form; failing that, how this person usually likes to be reminded.
            remindBeforeMinutes: $request->choseReminder() ? $request->reminderMinutes() : $resolveLead->for($request->user()),
        );

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'diary'])
            ->with('status', 'Diary entry added.');
    }

    /**
     * The person an entry is for decides how early they are reminded of it,
     * whatever the person who set it chose. That is a change to the entry, so the
     * log records it against their name.
     */
    public function updateReminder(UpdateDiaryReminderRequest $request, Matter $matter, DiaryEntry $diaryEntry): RedirectResponse
    {
        Gate::authorize('work', $matter);

        abort_unless($diaryEntry->matter_id === $matter->getKey(), 404);
        abort_unless($diaryEntry->assigned_to === $request->user()->getKey(), 403);

        if ($diaryEntry->isPending()) {
            $diaryEntry->update(['remind_before_minutes' => $request->reminderMinutes()]);
        }

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'diary'])
            ->with('status', 'Reminder updated.');
    }

    public function complete(Matter $matter, DiaryEntry $diaryEntry, CompleteDiaryEntry $completeEntry): RedirectResponse
    {
        Gate::authorize('work', $matter);

        abort_unless($diaryEntry->matter_id === $matter->getKey(), 404);

        // An entry is a reminder of a time; until that time it is not the person's to tick off.
        if ($diaryEntry->isPending() && ! $diaryEntry->isDue()) {
            return redirect()
                ->route('matter.show', [$matter, 'tab' => 'diary'])
                ->withErrors(['diary' => 'This entry is not due until '.$diaryEntry->due_at->format('d M Y H:i').', so it cannot be marked done yet.'], 'diary_entries');
        }

        $completeEntry->complete($diaryEntry, request()->user());

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'diary'])
            ->with('status', 'Diary entry completed.');
    }
}
