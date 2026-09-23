<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterType;

/*
 * The three parts of a file reference that need to be persisted together.
 * The formatted value (e.g. "IOT/CIV/105/2026") is what people read; the
 * sequence and year are kept as columns so the next number can be found
 * without parsing the string.
 */
final readonly class MatterReference
{
    public function __construct(
        protected string $reference,
        protected string $code,
        protected int $sequenceNumber,
        protected int $openedYear,
    ) {}

    public static function for(Client $client, MatterType $type, ?int $year): static
    {
        $year ??= (int) now()->year;
        $sequence = self::nextSequence($type, $year);

        $value = sprintf(
            '%s/%s/%d/%d',
            $client->initials,
            $type->code,
            $sequence,
            $year,
        );

        return new self($value, $type->code, $sequence, $year);
    }

    protected static function nextSequence(MatterType $matterType, int $year): ?int
    {
        $highest = Matter::withTrashed()
            ->where('matter_type_id', $matterType->getKey())
            ->where('opened_year', $year)
            ->max('sequence_number');

        return (int) $highest + 1;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getOpenedYear(): ?int
    {
        return $this->openedYear;
    }

    public function getMatterType(): ?MatterType
    {
        return MatterType::withTrashed()
            ->where('code', $this->code)
            ->first();
    }

    public function getSequenceNumber(): ?int
    {
        return $this->sequenceNumber;
    }

    public function __toString(): string
    {
        return $this->reference;
    }
}
