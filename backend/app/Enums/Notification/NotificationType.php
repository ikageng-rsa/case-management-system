<?php

declare(strict_types=1);

namespace App\Enums\Notification;

/*
 * The things the system tells a person about, and so the things they get a say
 * in. Each is delivered in-app, and may also be emailed; a person chooses the
 * email. A new kind of notification is added here, and shows up for everyone
 * with the default until they choose.
 */
enum NotificationType: string
{
    case DiaryOverdue = 'diary_overdue';

    public function label(): string
    {
        return match ($this) {
            self::DiaryOverdue => 'A diary entry assigned to me is overdue',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DiaryOverdue => 'Sent for each entry that is past its due date and not yet done.',
        };
    }

    /** Whether the email is on until the person chooses otherwise. */
    public function emailByDefault(): bool
    {
        return match ($this) {
            self::DiaryOverdue => true,
        };
    }
}
