<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use App\Models\Invitation\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;

class SendInvitation
{
    /**
     * Sent at once rather than queued, so a mail problem is seen by the person
     * who is inviting, who can resend, and not found out about days later.
     */
    public function send(UserInvitation $invitation, string $token): void
    {
        Notification::route('mail', [$invitation->email => $invitation->name])
            ->notifyNow(new UserInvitationNotification($invitation, $token));

        $invitation->forceFill(['sent_at' => now()])->save();
    }
}
