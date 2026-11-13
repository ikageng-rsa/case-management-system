<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\TariffScale;
use App\Models\Billing\TariffAdjustment;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Reference\ActivityType;
use App\Support\Billing\TariffCsv;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportTariffSchedule
{
    /**
     * Read a tariff file into a draft schedule for the given date. Nothing is
     * saved unless every line is sound, and the file itself is kept with the
     * schedule. Each role starts with the markup it had on the latest published
     * tariff, so a new gazette usually needs only checking and publishing.
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
            $previous = TariffSchedule::query()->whereNotNull('published_at')->latestFirst()->with('adjustments')->first();

            $schedule = TariffSchedule::create([
                'name' => trim($name),
                'effective_from' => $effectiveFrom->toDateString(),
            ]);

            $schedule->items()->createMany($items);
            $schedule->adjustments()->createMany(
                $previous?->adjustments->map(fn (TariffAdjustment $adjustment) => $adjustment->only(['role_id', 'kind', 'value']))->all() ?? [],
            );
            $schedule->attachSource($file);

            return $schedule;
        });
    }

    /**
     * The lines the rows describe, or every problem with them.
     *
     * @param  list<array<string, string|int>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function items(array $rows): array
    {
        $activityTypes = ActivityType::withTrashed()->get()->keyBy('code');

        $items = [];
        $problems = [];
        $seen = [];

        foreach ($rows as $row) {
            $code = strtoupper((string) $row['activity_code']);
            $type = $activityTypes->get($code);
            $found = [];

            if ($type === null) {
                $found[] = "there is no kind of work with the code \"{$row['activity_code']}\"";
            }

            if ($type?->trashed()) {
                $found[] = "{$type->code} has been retired";
            }

            $rates = [];

            foreach (TariffScale::cases() as $scale) {
                $cell = (string) $row['scale_'.strtolower($scale->value)];
                $rates[TariffItem::scaleColumn($scale)] = $cell === '' ? null : TariffCsv::cents($cell);

                if ($cell !== '' && $rates[TariffItem::scaleColumn($scale)] === null) {
                    $found[] = "the scale {$scale->value} rate \"{$cell}\" is not an amount in rand, such as 49 or 49.50";
                }
            }

            if (array_filter($rates, fn (?int $rate) => $rate !== null) === [] && $found === []) {
                $found[] = 'it has no rate on any scale';
            }

            if ($found === [] && isset($seen[$code])) {
                $found[] = "{$code} is already on line {$seen[$code]}";
            }

            if ($found !== []) {
                $problems[] = "Line {$row['line']}: ".implode('; ', $found).'.';

                continue;
            }

            $seen[$code] = $row['line'];

            $items[] = ['activity_type_id' => $type->getKey(), ...$rates];
        }

        if ($problems !== []) {
            throw new TariffImportFailed($problems);
        }

        return $items;
    }
}
