<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\User;

/*
 * Where a person gets a kind of notification: as they chose it, or failing that,
 * as the kind defaults it. An empty list is a choice too, and means nowhere.
 */
class ResolveNotificationChannels
{
    /** @return array<int, string> */
    public function for(User $user, NotificationType $type): array
    {
        $chosen = $user->notificationPreferences->firstWhere('type', $type->value);

        if ($chosen === null) {
            return $type->defaultChannels();
        }

        return array_values(array_filter(
            NotificationType::channels(),
            fn (string $channel) => (bool) $chosen->{$channel},
        ));
    }

    /**
     * Every kind at once, for showing what someone has set up.
     *
     * @return array<string, array<int, string>> Keyed by the kind's value
     */
    public function all(User $user): array
    {
        return collect(NotificationType::cases())
            ->mapWithKeys(fn (NotificationType $type) => [$type->value => $this->for($user, $type)])
            ->all();
    }
}
