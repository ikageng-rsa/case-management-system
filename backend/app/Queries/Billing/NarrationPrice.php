<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\TariffScale;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Narration\Narration;
use App\Models\User\Role;
use Illuminate\Support\Collection;

/*
 * What a narration is worth on the tariff: the tariff in force on the day the
 * work was done, the matter's scale unless another is given, and the rate the
 * author charges. Someone with more than one role charges at whichever of them
 * gives the highest rate. The worth is the narration's billable units at that
 * rate. Work that cannot be priced says why, so it can be put right.
 *
 * The published tariffs are read once and kept for the life of the object, so
 * pricing every narration on a matter costs a handful of queries.
 */
class NarrationPrice
{
    /** @var Collection<int, TariffSchedule>|null */
    protected ?Collection $schedules = null;

    /**
     * Either ['priced' => true, 'units', 'rateCents', 'amountCents', 'scale', 'role', 'schedule']
     * or ['priced' => false, 'reason'].
     *
     * @return array<string, mixed>
     */
    public function for(Narration $narration, ?TariffScale $scale = null): array
    {
        if (! $narration->activityType->default_billable) {
            return $this->unpriced('This kind of work is not billed.');
        }

        $schedule = $this->scheduleOn($narration);

        if ($schedule === null) {
            return $this->unpriced('No tariff was in force on '.$narration->occurred_at->format('j M Y').'.');
        }

        $scale ??= $narration->matter->tariff_scale;

        if ($scale === null) {
            return $this->unpriced('The matter has no tariff scale.');
        }

        $item = $schedule->items->firstWhere('activity_type_id', $narration->activity_type_id);

        if ($item?->rate($scale) === null) {
            return $this->unpriced("{$schedule->name} has no scale {$scale->value} rate for {$narration->activityType->name}.");
        }

        [$rate, $role] = $this->authorsRate($schedule, $item, $scale, $narration->author->roles);
        $units = $narration->billableUnits();

        return [
            'priced' => true,
            'units' => $units,
            'rateCents' => $rate,
            'amountCents' => $units * $rate,
            'scale' => $scale,
            'role' => $role,
            'schedule' => $schedule,
        ];
    }

    /**
     * The highest rate among the author's roles, and the role it comes from;
     * the gazette rate when they have none.
     *
     * @param  Collection<int, Role>  $roles
     * @return array{0: int, 1: ?Role}
     */
    protected function authorsRate(TariffSchedule $schedule, TariffItem $item, TariffScale $scale, Collection $roles): array
    {
        if ($roles->isEmpty()) {
            return [$schedule->rateFor($item, $scale, null), null];
        }

        $role = $roles->sortByDesc(fn (Role $role) => $schedule->rateFor($item, $scale, $role))->first();

        return [$schedule->rateFor($item, $scale, $role), $role];
    }

    protected function scheduleOn(Narration $narration): ?TariffSchedule
    {
        $this->schedules ??= TariffSchedule::query()
            ->whereNotNull('published_at')
            ->latestFirst()
            ->with(['items', 'adjustments'])
            ->get();

        return $this->schedules->first(fn (TariffSchedule $schedule) => $schedule->effective_from->lte($narration->occurred_at));
    }

    /** @return array{priced: false, reason: string} */
    protected function unpriced(string $reason): array
    {
        return ['priced' => false, 'reason' => $reason];
    }
}
