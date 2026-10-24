<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use App\Models\Invitation\UserInvitation;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class InviteUser
{
    public function __construct(private readonly SendInvitation $send) {}

    /**
     * @param  array<int, int>  $roles  Role ids the person joins with
     * @return array{0: UserInvitation, 1: string} The invitation and the token its link carries
     */
    public function invite(string $name, string $email, array $roles, int $lifetimeDays, ?User $by = null): array
    {
        $email = mb_strtolower(trim($email));

        if (User::query()->where('email', $email)->exists()) {
            throw new EmailAlreadyInvited($email, isUser: true);
        }

        if (UserInvitation::query()->where('email', $email)->pending()->exists()) {
            throw new EmailAlreadyInvited($email, isUser: false);
        }

        [$invitation, $token] = DB::transaction(function () use ($name, $email, $roles, $lifetimeDays, $by): array {
            $token = UserInvitation::newToken();

            $invitation = new UserInvitation([
                'name' => trim($name),
                'email' => $email,
                'roles' => array_values(array_map('intval', $roles)),
                'lifetime_days' => $lifetimeDays,
            ]);

            $invitation->token_hash = UserInvitation::hash($token);
            $invitation->expires_at = now()->addDays($lifetimeDays);
            $invitation->inviter()->associate($by);
            $invitation->save();

            return [$invitation, $token];
        });

        $this->send->send($invitation, $token);

        return [$invitation, $token];
    }
}
