<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\Matter\Assignment;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use App\Services\Matters\AssignUserToMatter;
use App\Services\Notifications\NotifyUser;
use App\Services\Roles\EnsureAdministrationRemains;
use Illuminate\Support\Facades\DB;

/*
 * Takes a person out of the firm without taking them out of its history. Their
 * name stays on everything they did; what they were still to do is put in
 * someone's hands first, and they can no longer sign in.
 */
class DeactivateUser
{
    public function __construct(
        private readonly UserWorkload $workload,
        private readonly AssignUserToMatter $assign,
        private readonly NotifyUser $notify,
        private readonly EnsureAdministrationRemains $ensureAdministration,
    ) {}

    /**
     * @param  array<string, string|null>  $successors  Matter id => the person who takes over there
     *
     * @throws CannotDeactivateUser when a matter that needs someone has none chosen
     */
    public function deactivate(User $user, User $by, string $reason, array $successors): void
    {
        if ($user->is($by)) {
            throw CannotDeactivateUser::yourself();
        }

        $items = $this->workload->for($user);
        $chosen = User::query()->whereIn('id', array_filter($successors))->get()->keyBy('id');

        $missing = $items
            ->filter(fn (array $item) => $item['needs_successor'])
            ->filter(function (array $item) use ($successors, $chosen, $user) {
                $pick = $chosen[$successors[$item['matter']->getKey()] ?? null] ?? null;

                return $pick === null || $pick->is($user);
            });

        if ($missing->isNotEmpty()) {
            throw CannotDeactivateUser::noSuccessor($missing->map(fn (array $item) => $item['matter']->reference)->implode(', '));
        }

        DB::transaction(function () use ($user, $by, $reason, $items, $successors, $chosen): void {
            $handedOver = [];

            foreach ($items as $item) {
                $matter = $item['matter'];
                $successor = $chosen[$successors[$matter->getKey()] ?? null] ?? null;

                if ($successor !== null) {
                    // Taking the seat retires the person leaving; anyone else is brought onto the team to carry the entries.
                    if ($item['responsible']) {
                        $this->assign->assign($matter, $successor, Assignment::Responsible);
                    } elseif (! $item['team']->contains(fn (User $member) => $member->is($successor))) {
                        $this->assign->assign($matter, $successor, Assignment::Assisting);
                    }

                    foreach ($item['entries'] as $entry) {
                        $entry->assigned_to = $successor->getKey();
                        $entry->save();

                        $this->notify->about($successor, new DiaryEntryAssigned($entry, $by));
                    }

                    // Written as the sentence it is: the log is read by people, and a list reads as one.
                    $count = $item['entries']->count();
                    $handedOver[] = $matter->reference.' to '.$successor->name
                        .($count > 0 ? ' ('.$count.' diary '.($count === 1 ? 'entry' : 'entries').')' : '');
                }

                $matter->assignments()->active()->where('user_id', $user->getKey())->get()->each->unassign();
            }

            $user->recordEvent('deactivated', $by->name.' deactivated user '.$user->name, [
                'reason' => $reason,
                'handed_over' => $handedOver,
            ]);

            $user->forceFill(['remember_token' => null])->save();
            $user->delete();

            // Locked out of everything at once, and any reset link already sent is useless.
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $this->ensureAdministration->check();
        });
    }
}
