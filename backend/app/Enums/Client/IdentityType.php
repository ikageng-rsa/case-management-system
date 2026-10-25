<?php

declare(strict_types=1);

namespace App\Enums\Client;

/*
 * What kind of document the number on a client's record belongs to. Clients come
 * from several countries, so a South African ID is one of the possibilities and
 * not the assumption.
 */
enum IdentityType: string
{
    case SouthAfricanId = 'sa_id';
    case Passport = 'passport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SouthAfricanId => 'South African ID',
            self::Passport => 'Passport',
            self::Other => 'Other ID document',
        };
    }

    /** What a masked number is introduced with, so a passport is not mistaken for an ID. A South African ID needs none. */
    public function prefix(): ?string
    {
        return match ($this) {
            self::SouthAfricanId => null,
            self::Passport => 'passport',
            self::Other => 'ID',
        };
    }

    public function isSouthAfrican(): bool
    {
        return $this === self::SouthAfricanId;
    }
}
