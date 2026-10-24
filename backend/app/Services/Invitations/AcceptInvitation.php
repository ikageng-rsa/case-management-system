<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use App\Models\User\Role;
use App\Models\User\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcceptInvitation
{
    /**
     * Turns an invitation into a user, once. The invitation is locked while it
     * is used, so two people following the link at the same moment cannot both
     * get an account from it. Roles are given as ids; any that have since been
     * deleted are skipped.
     */
    public function accept(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password): User {
            $invitation = UserInvitation::query()
                ->where('token_hash', UserInvitation::hash($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isPending()) {
                throw new InvitationNoLongerValid;
            }

            if (User::query()->where('email', $invitation->email)->exists()) {
                throw new InvitationNoLongerValid;
            }

            $user = new User(['name' => $invitation->name, 'email' => $invitation->email]);
            $user->password = Hash::make($password);
            $user->email_verified_at = now();
            $user->save();

            $user->syncRoles(Role::query()->whereIn('id', $invitation->roles)->get());

            $invitation->forceFill(['accepted_at' => now()])->save();

            $invitation->recordEvent(
                'accepted',
                sprintf('%s accepted the invitation and joined as a user', $user->name),
                ['user_id' => $user->getKey()],
            );

            return $user;
        });
    }
}
