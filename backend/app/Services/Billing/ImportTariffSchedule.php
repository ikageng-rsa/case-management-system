<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\TariffScale;
use App\Models\Billing\TariffSchedule;
use App\Models\Reference\ActivityType;
use App\Models\User\Role;
use App\Support\Billing\TariffCsv;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportTariffSchedule
{
    /**
     * Read a tariff file into a new schedule that is in force from the given
     * date. Nothing is saved unless every line is sound, and the file itself is
     * kept with the schedule.
     */
    public function import(string $name, CarbonInterface $effectiveFrom, UploadedFile $file): TariffSchedule
    {
        try {
            $rows = TariffCsv::read($file->getRealPath());
        } catch (InvalidArgumentException $e) {
            throw new TariffImportFailed([$e->getMessage()]);
        }

        if ($rows === []) {
            throw new TariffImportFailed(['The file has no rates under its headings.']);
        }

        $items = $this->items($rows);

        return DB::transaction(function () use ($name, $effectiveFrom, $file, $items): TariffSchedule {
            $schedule = TariffSchedule::create([
                'name' => trim($name),
                'effective_from' => $effectiveFrom->toDateString(),
            ]);

            $schedule->items()->createMany($items);
            $schedule->attachSource($file);

            return $schedule;
        });
    }

    /**
     * The rates the rows describe, or every problem with them.
     *
     * @param  list<array<string, string|int>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function items(array $rows): array
    {
        $activityTypes = ActivityType::withTrashed()->get()->keyBy('code');
        $roles = Role::query()->get()->keyBy(fn (Role $role) => strtolower($role->name));

        $items = [];
        $problems = [];
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $found = [];

            $type = $activityTypes->get(strtoupper((string) $row['activity_code']));
            $scale = $row['scale'] === '' ? null : TariffScale::tryFrom(strtoupper((string) $row['scale']));
            $role = $row['role'] === '' ? null : $roles->get(strtolower((string) $row['role']));
            $unitRate = TariffCsv::cents((string) $row['unit_rate']);
            $timeRate = $row['time_rate'] === '' ? null : TariffCsv::cents((string) $row['time_rate']);
            $block = $row['time_block_minutes'] === '' ? null : filter_var($row['time_block_minutes'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]]);

            if ($type === null) {
                $found[] = "there is no kind of work with the code \"{$row['activity_code']}\"";
            }

            if ($type?->trashed()) {
                $found[] = "{$type->code} has been retired";
            }

            if ($row['scale'] !== '' && $scale === null) {
                $found[] = "the scale \"{$row['scale']}\" is not one of A, B, C or D";
            }

            if ($row['role'] !== '' && $role === null) {
                $found[] = "there is no role called \"{$row['role']}\"";
            }

            if ($unitRate === null) {
                $found[] = "the unit rate \"{$row['unit_rate']}\" is not an amount in rand, such as 49 or 49.50";
            }

            $found = [...$found, ...$this->timeProblems($row, $type, $timeRate, $block)];

            $key = implode('|', [$type?->code, $scale?->value, $role?->getKey()]);

            if ($found === [] && isset($seen[$key])) {
                $found[] = "it repeats the rate on line {$seen[$key]} for the same kind of work, scale and role";
            }

            if ($found !== []) {
                $problems[] = "Line {$line}: ".implode('; ', $found).'.';

                continue;
            }

            $seen[$key] = $line;

            $items[] = [
                'activity_type_id' => $type->getKey(),
                'scale' => $scale,
                'role_id' => $role?->getKey(),
                'unit_rate_cents' => $unitRate,
                'time_rate_cents' => $timeRate,
                'time_block_minutes' => $block ?: null,
            ];
        }

        if ($problems !== []) {
            throw new TariffImportFailed($problems);
        }

        return $items;
    }

    /**
     * Time is charged on top only for work counted in something else, and a
     * time rate needs the size of the block it is charged per.
     *
     * @param  array<string, string|int>  $row
     * @return list<string>
     */
    protected function timeProblems(array $row, ?ActivityType $type, ?int $timeRate, int|false|null $block): array
    {
        $found = [];

        if ($row['time_rate'] !== '' && $timeRate === null) {
            $found[] = "the time rate \"{$row['time_rate']}\" is not an amount in rand";
        }

        if ($block === false) {
            $found[] = "the time block \"{$row['time_block_minutes']}\" is not a whole number of minutes";
        }

        if (($row['time_rate'] === '') !== ($row['time_block_minutes'] === '')) {
            $found[] = 'a time rate and its block of minutes go together';
        }

        if ($row['time_rate'] !== '' && $type?->measure->isTimed()) {
            $found[] = "{$type->code} is already counted in minutes, so it takes no separate time rate";
        }

        return $found;
    }
}
