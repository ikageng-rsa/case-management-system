<?php

declare(strict_types=1);

namespace App\Services\Narrations;

use App\Models\ActivityType;
use App\Models\Court;
use App\Models\Matter;
use App\Models\Narration;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordNarration
{
    public function record(
        Matter $matter,
        ActivityType $activityType,
        User $author,
        string $body,
        float $quantity,
        ?Court $court = null,
        ?CarbonInterface $occurredAt = null,
    ): Narration {
        // Appearances are tied to the court attended, which drives the tariff;
        // everything else is not before a court.
        if ($activityType->requires_court && $court === null) {
            throw new InvalidArgumentException(
                "Activity type [{$activityType->code}] requires the court to be given.",
            );
        }

        if (! $activityType->requires_court) {
            $court = null;
        }

        return DB::transaction(function () use ($matter, $activityType, $author, $body, $quantity, $court, $occurredAt): Narration {
            $narration = new Narration([
                'body' => $body,
                'quantity' => $quantity,
                'occurred_at' => $occurredAt,
            ]);

            $narration->matter()->associate($matter);
            $narration->activityType()->associate($activityType);
            $narration->author()->associate($author);

            if ($court !== null) {
                $narration->court()->associate($court);
            }

            $narration->save();

            return $narration;
        });
    }
}
