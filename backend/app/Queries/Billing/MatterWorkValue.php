<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Models\Matter\Matter;
use App\Models\Narration\Narration;

/*
 * What the billable work recorded on a matter comes to on the tariff, for its
 * billing tab, with the narrations that could not be priced and why. It is a
 * figure for the work done, not an amount anyone owes.
 */
class MatterWorkValue
{
    public function __construct(
        protected NarrationPrice $price,
    ) {}

    /** @return array<string, mixed> */
    public function for(Matter $matter): array
    {
        $narrations = $matter->narrations()
            ->whereHas('activityType', fn ($query) => $query->billable())
            ->with(['activityType.timeActivityType', 'author.roles'])
            ->latestFirst()
            ->get()
            ->each(fn (Narration $narration) => $narration->setRelation('matter', $matter));

        $prices = $narrations->map(fn (Narration $narration) => ['narration' => $narration, ...$this->price->for($narration)]);
        [$priced, $unpriced] = $prices->partition(fn (array $price) => $price['priced']);

        return [
            'workValueCents' => (int) $priced->sum('amountCents'),
            'pricedCount' => $priced->count(),
            'unpricedWork' => $unpriced->values(),
        ];
    }
}
