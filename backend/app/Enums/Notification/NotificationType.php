<?php

declare(strict_types=1);

namespace App\Enums\Notification;

/*
 * The things the system tells a person about: everything that has to do with
 * them. Each can be shown in the system, emailed, both, or neither, and the
 * person chooses. A new kind of notification is added here, and everyone gets
 * its default until they choose.
 */
enum NotificationType: string
{
    case MatterAssigned = 'matter_assigned';
    case MatterUnassigned = 'matter_unassigned';
    case DiaryAssigned = 'diary_assigned';
    case DiaryOverdue = 'diary_overdue';

    public const CHANNEL_SYSTEM = 'database';

    public const CHANNEL_EMAIL = 'mail';

    public function label(): string
    {
        return match ($this) {
            self::MatterAssigned => 'I am assigned to a matter',
            self::MatterUnassigned => 'I am taken off a matter',
            self::DiaryAssigned => 'A diary entry is assigned to me',
            self::DiaryOverdue => 'A diary entry assigned to me is overdue',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MatterAssigned => 'When someone puts you on a matter, in any capacity. Not sent for what you assign to yourself.',
            self::MatterUnassigned => 'When someone takes you off a matter you were on.',
            self::DiaryAssigned => 'When someone else adds a diary entry for you to do.',
            self::DiaryOverdue => 'For each entry that is past its due date and not yet done.',
        };
    }

    /**
     * The channels used until the person chooses.
     *
     * @return array<int, string>
     */
    public function defaultChannels(): array
    {
        return match ($this) {
            self::MatterAssigned, self::DiaryOverdue => [self::CHANNEL_SYSTEM, self::CHANNEL_EMAIL],
            self::MatterUnassigned, self::DiaryAssigned => [self::CHANNEL_SYSTEM],
        };
    }

    /** @return array<int, string> */
    public static function channels(): array
    {
        return [self::CHANNEL_SYSTEM, self::CHANNEL_EMAIL];
    }
}
