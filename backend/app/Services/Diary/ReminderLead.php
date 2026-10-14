<?php

declare(strict_types=1);

namespace App\Services\Diary;

/*
 * How long before a diary entry falls due its assignee is reminded
 */
final class ReminderLead
{
    /** @var array<int, string> Minutes, and how they read */
    public const PRESETS = [
        30 => '30 minutes before',
        60 => '1 hour before',
        1440 => '1 day before',
        4320 => '3 days before',
        7200 => '5 days before',
    ];

    /** The reminder is sent by a job that runs every few minutes, so closer than this is not possible. */
    public const MINIMUM = 5;

    public const MAXIMUM = 43200; // 30 days

    public const FIRM_DEFAULT = 1440;

    private const UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    /** @return array<int, string> */
    public static function units(): array
    {
        return array_keys(self::UNITS);
    }

    /**
     * What a form's three fields amount to, in minutes. "none" is no reminder,
     * a number is one of the presets, and "custom" is a number and a unit.
     */
    public static function minutes(string $choice, mixed $value = null, ?string $unit = null): ?int
    {
        return match ($choice) {
            'none', '' => null,
            'custom' => (int) $value * (self::UNITS[$unit] ?? 1),
            default => (int) $choice,
        };
    }

    /** "30 minutes", "1 hour", "3 days", "90 minutes": the largest whole unit it comes to. */
    public static function label(?int $minutes): string
    {
        if ($minutes === null) {
            return 'No reminder';
        }

        foreach ([['day', 1440], ['hour', 60], ['minute', 1]] as [$name, $size]) {
            if ($minutes % $size === 0) {
                $count = intdiv($minutes, $size);

                return $count.' '.$name.($count === 1 ? '' : 's').' before';
            }
        }

        return $minutes.' minutes before';
    }

    /**
     * @return array{choice: string, value: ?int, unit: string}
     */
    public static function asChoice(?int $minutes): array
    {
        if ($minutes === null) {
            return ['choice' => 'none', 'value' => null, 'unit' => 'hours'];
        }

        if (array_key_exists($minutes, self::PRESETS)) {
            return ['choice' => (string) $minutes, 'value' => null, 'unit' => 'hours'];
        }

        foreach (['days' => 1440, 'hours' => 60] as $unit => $size) {
            if ($minutes % $size === 0) {
                return ['choice' => 'custom', 'value' => intdiv($minutes, $size), 'unit' => $unit];
            }
        }

        return ['choice' => 'custom', 'value' => $minutes, 'unit' => 'minutes'];
    }
}
