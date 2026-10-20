<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Court;
use App\Models\Matter;
use Illuminate\Support\Facades\DB;

/*
 * Changes the particulars of a matter. Title, date and court are logged by the
 * model as any change is. The prescription date feeds the warnings that a claim
 * is about to be lost, so its change is recorded as an event of its own with the
 * old date, the new one and the reason, and is saved quietly so that it is logged
 * once and not twice.
 */
class UpdateMatterDetails
{
    /**
     * @param  array<string, mixed>  $data  Only the fields that were sent
     */
    public function update(Matter $matter, array $data, ?string $reason): void
    {
        DB::transaction(function () use ($matter, $data, $reason): void {
            $matter->fill(collect($data)->only(['title', 'instructed_at'])->all());

            // Matters that are not litigious are not before a court.
            if (array_key_exists('court_id', $data) && $matter->matterType->is_litigation) {
                $matter->court()->associate($data['court_id'] === null ? null : Court::findOrFail($data['court_id']));
            }

            $matter->save();

            if (array_key_exists('prescribes_at', $data)) {
                $this->changePrescription($matter, $data['prescribes_at'], (string) $reason);
            }
        });
    }

    private function changePrescription(Matter $matter, mixed $new, string $reason): void
    {
        $before = $matter->prescribes_at;

        $matter->forceFill(['prescribes_at' => $new]);

        if (! $matter->isDirty('prescribes_at')) {
            return;
        }

        $matter->saveQuietly();

        $matter->recordEvent('prescription_changed', 'Prescription date changed on matter '.$matter->reference, [
            'from' => $before?->format('d M Y'),
            'to' => $matter->prescribes_at?->format('d M Y'),
            'reason' => $reason,
        ]);
    }
}
