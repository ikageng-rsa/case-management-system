<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Diary\StoreDiaryEntryRequest;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DiaryEntryController extends Controller
{
    public function store(StoreDiaryEntryRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('work', $matter);

        $entry = new DiaryEntry($request->safe()->only(['body', 'due_at']));

        $entry->matter()->associate($matter);
        $entry->assignee()->associate(
            $request->filled('assigned_to') ? User::findOrFail($request->validated('assigned_to')) : $request->user(),
        );

        $entry->save();

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'diary'])
            ->with('status', 'Diary entry added.');
    }

    public function complete(Matter $matter, DiaryEntry $diaryEntry): RedirectResponse
    {
        Gate::authorize('work', $matter);

        abort_unless($diaryEntry->matter_id === $matter->getKey(), 404);

        if ($diaryEntry->isPending()) {
            $diaryEntry->markComplete(request()->user());
        }

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'diary'])
            ->with('status', 'Diary entry completed.');
    }
}
