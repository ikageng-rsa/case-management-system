<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveNotificationPreferences
{
    /**
     * Save a person's choices, a yes or no for each channel of each kind. A kind
     * or channel not mentioned is a no. Rows are saved through the model, so the
     * activity log records a change once and an unchanged row not at all.
     *
     * @param  array<string, array<string, mixed>>  $channels  Keyed by kind, then by channel
     */
    public function save(User $user, array $channels): void
    {
        DB::transaction(function () use ($user, $channels): void {
            foreach (NotificationType::cases() as $type) {
                $user->notificationPreferences()->updateOrCreate(
                    ['type' => $type->value],
                    collect(NotificationType::channels())
                        ->mapWithKeys(fn (string $channel) => [$channel => (bool) ($channels[$type->value][$channel] ?? false)])
                        ->all(),
                );
            }
        });

        $user->unsetRelation('notificationPreferences');
    }
}
