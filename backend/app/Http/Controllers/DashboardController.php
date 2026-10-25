<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User\User;
use App\Queries\Diary\DiaryAgenda;
use App\Queries\Matters\OpenMattersSummary;
use App\Queries\Narrations\RecentNarrations;
use App\Queries\Narrations\UnbilledTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        OpenMattersSummary $openMatters,
        UnbilledTime $unbilledTime,
        DiaryAgenda $diaryAgenda,
        RecentNarrations $recentNarrations,
    ): View {
        /** @var User $user */
        $user = $request->user();

        return view('dashboard', [
            'recentNarrations' => $recentNarrations->for($user),
        ] + $openMatters->for($user) + $unbilledTime->for($user) + $diaryAgenda->for($user));
    }
}
