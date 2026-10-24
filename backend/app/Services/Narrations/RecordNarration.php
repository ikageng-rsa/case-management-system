<?php

declare(strict_types=1);

namespace App\Services\Narrations;

use App\Models\ActivityType;
use App\Models\Matter\Matter;
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
        ?CarbonInterface $occurredAt = null,
    ): Narration {
        // An appearance is before a court, and the court is the matter's own.
        if ($activityType->requires_court && $matter->court_id === null) {
            throw new InvalidArgumentException(
                "Activity type [{$activityType->code}] requires the matter to have a court.",
            );
        }

        return DB::transaction(function () use ($matter, $activityType, $author, $body, $quantity, $occurredAt): Narration {
            $narration = new Narration([
                'body' => $body,
                'quantity' => $quantity,
                'occurred_at' => $occurredAt,
            ]);

            $narration->matter()->associate($matter);
            $narration->activityType()->associate($activityType);
            $narration->author()->associate($author);

            $narration->save();

            return $narration;
        });
    }
}
