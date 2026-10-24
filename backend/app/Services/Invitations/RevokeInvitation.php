<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use App\Models\Invitation\UserInvitation;
use Illuminate\Support\Facades\Auth;

class RevokeInvitation
{
    public function revoke(UserInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw new InvitationNoLongerValid;
        }

        if (! $invitation->isRevoked()) {
            $invitation->forceFill(['revoked_at' => now()])->save();

            $invitation->recordEvent(
                'revoked',
                sprintf('%s withdrew the invitation for %s', Auth::user()?->name ?? 'System', $invitation->name),
            );
        }
    }
}
