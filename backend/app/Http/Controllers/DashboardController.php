<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Queries\Diary\DiaryAgenda;
use App\Queries\Matters\OpenMattersSummary;
use App\Queries\Narrations\UnbilledTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        OpenMattersSummary $openMatters,
        UnbilledTime $unbilledTime,
        DiaryAgenda $diaryAgenda,
    ): View {
        /** @var User $user */
        $user = $request->user();

        $seesAllMatters = $user->can('viewAny', Matter::class);

        return view('dashboard', [
            'recentNarrations' => Narration::query()
                ->when(! $seesAllMatters, fn (Builder $query) => $query->by($user))
                ->with(['matter', 'author'])
                ->latestFirst()
                ->limit(4)
                ->get(),
        ] + $openMatters->for($user) + $unbilledTime->for($user) + $diaryAgenda->for($user));
    }
}
