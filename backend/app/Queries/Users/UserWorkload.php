<?php

declare(strict_types=1);

namespace App\Queries\Users;

use App\Enums\Matter\Assignment;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Illuminate\Support\Collection;

/*
 * What a person is carrying on matters that are still open, matter by matter:
 * the seats they hold and the diary entries still to be done by them. This is
 * what has to be put in someone else's hands before they can be deactivated.
 * Closed matters are history and are left as they are.
 */
class UserWorkload
{
    /**
     * @return Collection<int, array{matter: Matter, capacities: Collection<int, Assignment>, responsible: bool, entries: Collection<int, DiaryEntry>, needs_successor: bool, team: Collection<int, User>}>
     */
    public function for(User $user): Collection
    {
        $seats = MatterAssignment::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->whereHas('matter', fn ($matter) => $matter->open())
            ->get()
            ->groupBy('matter_id');

        $entries = DiaryEntry::query()
            ->pending()
            ->where('assigned_to', $user->getKey())
            ->whereHas('matter', fn ($matter) => $matter->open())
            ->orderBy('due_at')
            ->get()
            ->groupBy('matter_id');

        $matters = Matter::query()
            ->whereIn('id', $seats->keys()->merge($entries->keys())->unique())
            ->with(['assignments' => fn ($query) => $query->active()->with('user')])
            ->orderBy('reference')
            ->get();

        return $matters->map(function (Matter $matter) use ($user, $seats, $entries): array {
            $capacities = ($seats[$matter->getKey()] ?? collect())->map->capacity->values();
            $pending = $entries[$matter->getKey()] ?? collect();
            $responsible = $capacities->contains(Assignment::Responsible);

            return [
                'matter' => $matter,
                'capacities' => $capacities,
                'responsible' => $responsible,
                'entries' => $pending,
                // The person who is responsible must be replaced; anyone else only
                // when there is work of theirs that would otherwise be left behind.
                'needs_successor' => $responsible || $pending->isNotEmpty(),
                'team' => $matter->assignments->pluck('user')->filter()->unique('id')
                    ->reject(fn (User $member) => $member->is($user))->values(),
            ];
        })->values();
    }
}
