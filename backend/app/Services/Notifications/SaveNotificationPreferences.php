<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class SaveNotificationPreferences
{
    /**
     * Save a person's choices, a yes or no for each channel of each kind. A kind
     * or channel not mentioned is a no
     *
     * @param  array<string, array<string, mixed>>  $channels  Keyed by kind, then by channel
     * @param  int|null  $leadMinutes  How long before an entry falls due they are reminded, by default
     */
    public function save(User $user, array $channels, ?int $leadMinutes = null): void
    {
        DB::transaction(function () use ($user, $channels, $leadMinutes): void {
            foreach (NotificationType::cases() as $type) {
                $values = collect(NotificationType::channels())
                    ->mapWithKeys(fn (string $channel) => [$channel => (bool) ($channels[$type->value][$channel] ?? false)])
                    ->all();

                // How early, for the kinds that have it, is kept with the choice of where.
                if ($type->hasLeadTime()) {
                    $values['lead_minutes'] = $leadMinutes;
                }

                $user->notificationPreferences()->updateOrCreate(['type' => $type->value], $values);
            }
        });

        $user->unsetRelation('notificationPreferences');
    }
}
