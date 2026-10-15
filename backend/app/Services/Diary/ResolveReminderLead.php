<?php

declare(strict_types=1);

namespace App\Services\Diary;

use App\Enums\Notification\NotificationType;
use App\Models\User;

class ResolveReminderLead
{
    /** The lead a person starts a new entry with: their own default, or the firm's. */
    public function for(User $user): int
    {
        $chosen = $user->notificationPreferences
            ->firstWhere('type', NotificationType::DiaryDueSoon->value)
            ?->lead_minutes;

        return $chosen ?? ReminderLead::FIRM_DEFAULT;
    }
}
