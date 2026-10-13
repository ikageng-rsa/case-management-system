<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use App\Models\UserInvitation;
use Illuminate\Support\Facades\Auth;

class ResendInvitation
{
    public function __construct(private readonly SendInvitation $send) {}

    /**
     * A resend is a fresh link with a fresh lifetime of the same length, and the
     * old link stops working. That also revives an invitation that ran out.
     */
    public function resend(UserInvitation $invitation): string
    {
        if ($invitation->isAccepted() || $invitation->isRevoked()) {
            throw new InvitationNoLongerValid;
        }

        $token = UserInvitation::newToken();

        $invitation->forceFill([
            'token_hash' => UserInvitation::hash($token),
            'expires_at' => now()->addDays($invitation->lifetime_days),
        ])->save();

        $this->send->send($invitation, $token);

        $invitation->recordEvent(
            'resent',
            sprintf('%s resent the invitation for %s', Auth::user()?->name ?? 'System', $invitation->name),
        );

        return $token;
    }
}
