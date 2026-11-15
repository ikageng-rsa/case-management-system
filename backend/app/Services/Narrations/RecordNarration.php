<?php

declare(strict_types=1);

namespace App\Services\Narrations;

use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
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
        ?int $minutes = null,
    ): Narration {
        // An appearance is before a court, and the court is the matter's own.
        if ($activityType->requires_court && $matter->court_id === null) {
            throw new InvalidArgumentException(
                "Activity type [{$activityType->code}] requires the matter to have a court.",
            );
        }

        return DB::transaction(function () use ($matter, $activityType, $author, $body, $quantity, $occurredAt, $minutes): Narration {
            $narration = new Narration([
                'body' => $body,
                'quantity' => $quantity,
                // Time is kept only for work whose kind charges it on top.
                'minutes' => $activityType->chargesTimeOnTop() ? $minutes : null,
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
