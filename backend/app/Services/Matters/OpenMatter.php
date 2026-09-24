<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Client;
use App\Models\Court;
use App\Models\Matter;
use App\Models\MatterType;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OpenMatter
{
    /** SQLSTATE for an integrity-constraint violation */
    private const INTEGRITY_VIOLATION = '23000';

    private const MAX_ATTEMPTS = 3;

    public function open(
        Client $client,
        MatterType $matterType,
        ?string $title = null,
        ?Court $court = null,
        ?CarbonInterface $instructedAt = null,
    ): Matter {
        $instructedAt ??= now();

        // Non litigious matters are not before a court
        if (! $matterType->is_litigation) {
            $court = null;
        }

        // Prescription runs from the instruction date, when the type prescribes.
        $prescribesAt = $matterType->prescribes()
            ? $instructedAt->copy()->addMonths((int) $matterType->default_prescription_months)
            : null;

        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return $this->persist($client, $matterType, $court, $title, $instructedAt, $prescribesAt);
            } catch (QueryException $e) {
                if ($attempts >= self::MAX_ATTEMPTS || ! $this->isDuplicateReference($e)) {
                    throw $e;
                }
            }
        }
    }

    private function persist(
        Client $client,
        MatterType $matterType,
        ?Court $court,
        ?string $title,
        CarbonInterface $instructedAt,
        ?CarbonInterface $prescribesAt,
    ): Matter {
        return DB::transaction(function () use ($client, $matterType, $court, $title, $instructedAt, $prescribesAt): Matter {
            $reference = MatterReference::for($client, $matterType, (int) $instructedAt->year);

            $matter = new Matter([
                'reference' => $reference->getReference(),
                'sequence_number' => $reference->getSequenceNumber(),
                'opened_year' => $reference->getOpenedYear(),
                'title' => $title,
                'instructed_at' => $instructedAt,
                'prescribes_at' => $prescribesAt,
            ]);

            $matter->client()->associate($client);
            $matter->matterType()->associate($matterType);

            if ($court !== null) {
                $matter->court()->associate($court);
            }

            $matter->save();

            return $matter;
        });
    }

    private function isDuplicateReference(QueryException $e): bool
    {
        return (string) $e->getCode() === self::INTEGRITY_VIOLATION;
    }
}
