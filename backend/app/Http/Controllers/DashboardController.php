<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Narration\ActivityMeasure;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\Narration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $seesAllMatters = $user->can('viewAny', Matter::class);

        $matters = Matter::query()
            ->open()
            ->when(! $seesAllMatters, fn (Builder $query) => $query->assignedTo($user))
            ->with(['client', 'assignedUsers', 'diaryEntries' => fn ($query) => $query->pending()])
            ->get();

        $newIntakes = $matters->filter(
            fn (Matter $matter) => $matter->instructed_at?->isAfter(now()->subDays(7)) ?? false,
        );

        $unbilledNarrations = $this->unbilledNarrations($seesAllMatters ? null : $user)->get(['quantity']);
        $unbilledMinutes = (float) $unbilledNarrations->sum('quantity');

        return view('dashboard', [
            'openMattersCount' => $matters->count(),
            'openedThisWeekCount' => $newIntakes->count(),
            'awaitingActionCount' => DiaryEntry::query()->assignedTo($user)->pending()->count(),
            'overdueCount' => DiaryEntry::query()->assignedTo($user)->overdue()->count(),
            'unbilledHours' => $unbilledMinutes / 60,
            'unbilledEntriesCount' => $unbilledNarrations->count(),
            'newIntakesCount' => $newIntakes->count(),
            'unassignedIntakesCount' => $newIntakes->filter(
                fn (Matter $matter) => $matter->assignedUsers->isEmpty(),
            )->count(),
            'mattersNeedingAttention' => $this->mattersNeedingAttention($matters),
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
        ]);
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

    /**
     * @param  Collection<int, Matter>  $matters
     * @return Collection<int, array{matter: Matter, label: string, tone: string, nextDate: ?Carbon}>
     */
    protected function mattersNeedingAttention(Collection $matters): Collection
    {
        return $matters
            ->sortBy(fn (Matter $matter) => $this->attentionRank($matter))
            ->take(5)
            ->map(fn (Matter $matter) => [
                'matter' => $matter,
                'label' => $this->attentionLabel($matter),
                'tone' => $this->attentionTone($matter),
                'nextDate' => $matter->diaryEntries->sortBy('due_at')->first()?->due_at,
            ])
            ->values();
    }

    protected function attentionRank(Matter $matter): int
    {
        return match ($this->attentionLabel($matter)) {
            'Prescribed', 'Overdue action' => 0,
            'Prescribing soon' => 1,
            default => 2,
        };
    }

    /*
     * A stand-in for a real matter status until one exists — see the matter
     * status discussion. Priority: prescribed, then overdue diary work, then
     * an approaching prescription date, then nothing in particular.
     */
    protected function attentionLabel(Matter $matter): string
    {
        return match (true) {
            $matter->hasPrescribed() => 'Prescribed',
            $matter->diaryEntries->contains(fn (DiaryEntry $entry) => $entry->isOverdue()) => 'Overdue action',
            $matter->prescribes_at?->isBefore(now()->addDays(30)) === true => 'Prescribing soon',
            default => 'On track',
        };
    }

    protected function attentionTone(Matter $matter): string
    {
        return match ($this->attentionLabel($matter)) {
            'Prescribed', 'Overdue action' => 'danger',
            'Prescribing soon' => 'warning',
            default => 'success',
        };
    }
}
