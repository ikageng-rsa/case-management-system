<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Narration\ActivityMeasure;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Queries\Matters\OpenMattersSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OpenMattersSummary $openMatters): View
    {
        /** @var User $user */
        $user = $request->user();

        $seesAllMatters = $user->can('viewAny', Matter::class);

        $unbilledNarrations = $this->unbilledNarrations($seesAllMatters ? null : $user)->get(['quantity']);
        $unbilledMinutes = (float) $unbilledNarrations->sum('quantity');

        return view('dashboard', [
            'awaitingActionCount' => DiaryEntry::query()->assignedTo($user)->pending()->count(),
            'overdueCount' => DiaryEntry::query()->assignedTo($user)->overdue()->count(),
            'unbilledHours' => $unbilledMinutes / 60,
            'unbilledEntriesCount' => $unbilledNarrations->count(),
            'diaryToday' => DiaryEntry::query()
                ->assignedTo($user)
                ->pending()
                ->whereDate('due_at', today())
                ->with('matter')
                ->orderBy('due_at')
                ->get(),
            'recentNarrations' => Narration::query()
                ->when(! $seesAllMatters, fn (Builder $query) => $query->by($user))
                ->with(['matter', 'author'])
                ->latestFirst()
                ->limit(4)
                ->get(),
        ] + $openMatters->for($user));
    }

    protected function unbilledNarrations(?User $scopeToUser): Builder
    {
        return Narration::query()
            ->whereHas(
                'activityType',
                fn (Builder $query) => $query->billable()->where('measure', ActivityMeasure::Minutes),
            )
            ->when($scopeToUser, fn (Builder $query, User $user) => $query->by($user));
    }
}
