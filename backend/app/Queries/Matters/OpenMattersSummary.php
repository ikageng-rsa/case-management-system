<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 * The open matters someone can see, summed up for the dashboard: how many
 * there are, how many came in this week and still have no one on them, and
 * the few that most need attention.
 */
class OpenMattersSummary
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $matters = Matter::query()
            ->open()
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user))
            ->with(['client', 'assignedUsers', 'diaryEntries' => fn ($query) => $query->pending()])
            ->get();

        $newIntakes = $matters->filter(
            fn (Matter $matter) => $matter->instructed_at?->isAfter(now()->subDays(7)) ?? false,
        );

        return [
            'openMattersCount' => $matters->count(),
            'openedThisWeekCount' => $newIntakes->count(),
            'newIntakesCount' => $newIntakes->count(),
            'unassignedIntakesCount' => $newIntakes->filter(
                fn (Matter $matter) => $matter->assignedUsers->isEmpty(),
            )->count(),
            'mattersNeedingAttention' => $this->mattersNeedingAttention($matters),
        ];
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
