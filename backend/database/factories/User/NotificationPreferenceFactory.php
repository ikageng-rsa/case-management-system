<?php

declare(strict_types=1);

namespace Database\Factories\User;

use App\Enums\Notification\NotificationType;
use App\Models\User\NotificationPreference;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationType::DiaryOverdue->value,
            'database' => true,
            'mail' => true,
        ];
    }
}
