<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\Concerns;

use App\Enums\Client\IdentityType;
use App\Rules\Client\UniqueClientIdNumber;
use App\Rules\Client\ValidForeignIdentity;
use App\Rules\Client\ValidSouthAfricanId;
use App\Services\Clients\ResolveDateOfBirth;
use App\Support\Clients\NormaliseIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
 * The identity of an individual: what kind of document, its number, and the date of
 * birth. Clients come from several countries, so none of it is required, and what is
 * given is checked as the kind of document it says it is.
 */
trait ValidatesIdentity
{
    /** @return array<string, mixed> */
    protected function identityRules(?string $ignore = null): array
    {
        $type = $this->identityType();

        return [
            // Left out, the number is read as a South African ID, as every number was before passports.
            'id_type' => ['nullable', Rule::enum(IdentityType::class)],
            'id_number' => [
                'nullable',
                'string',
                $type->isSouthAfrican() ? new ValidSouthAfricanId : new ValidForeignIdentity,
                new UniqueClientIdNumber($ignore, $type),
            ],
            'date_of_birth' => ['nullable', 'date', 'after:1899-12-31', 'before:today'],
        ];
    }

    protected function identityType(): IdentityType
    {
        return IdentityType::tryFrom((string) $this->input('id_type')) ?? IdentityType::SouthAfricanId;
    }

    /**
     * A South African ID and a date of birth have to agree. Where the number is not being
     * changed, the one already on the record is what the date is checked against.
     */
    protected function checkBirthDateAgainstId(Validator $validator, ?string $storedNumber = null, ?IdentityType $storedType = null): void
    {
        if ($validator->errors()->hasAny(['id_number', 'id_type', 'date_of_birth']) || ! $this->filled('date_of_birth')) {
            return;
        }

        $type = $this->filled('id_number') ? $this->identityType() : $storedType;
        $number = $this->filled('id_number') ? NormaliseIdentifier::identity($type, (string) $this->input('id_number')) : $storedNumber;

        if ($type?->isSouthAfrican() && $number !== null
            && ! app(ResolveDateOfBirth::class)->agrees($number, CarbonImmutable::parse((string) $this->input('date_of_birth')))) {
            $validator->errors()->add('date_of_birth', 'The date of birth does not match the ID number.');
        }
    }
}
