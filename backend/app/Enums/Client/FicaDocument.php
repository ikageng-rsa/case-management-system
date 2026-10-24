<?php

declare(strict_types=1);

namespace App\Enums\Client;

/*
 * The documents FICA asks the firm to hold for a client, by kind of client. An
 * individual proves who they are and where they live; an entity proves that it
 * exists, where it trades, who runs it and who may sign for it.
 */
enum FicaDocument: string
{
    case IdentityDocument = 'identity_document';
    case ProofOfAddress = 'proof_of_address';

    case RegistrationDocuments = 'registration_documents';
    case ProofOfBusinessAddress = 'proof_of_business_address';
    case DirectorIdentities = 'director_identities';
    case SignatoryResolution = 'signatory_resolution';

    public function label(): string
    {
        return match ($this) {
            self::IdentityDocument => 'Copy of ID or passport',
            self::ProofOfAddress => 'Proof of residential address',
            self::RegistrationDocuments => 'Registration documents',
            self::ProofOfBusinessAddress => 'Proof of business address',
            self::DirectorIdentities => 'IDs of directors or members',
            self::SignatoryResolution => 'Resolution authorising the signatory',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::IdentityDocument => 'Certified copy of the ID or passport.',
            self::ProofOfAddress => 'A utility bill or bank statement not older than three months.',
            self::RegistrationDocuments => 'CIPC registration certificate (CoR14.3) and the latest list of directors (CoR39).',
            self::ProofOfBusinessAddress => 'A utility bill or lease not older than three months.',
            self::DirectorIdentities => 'One file per director or member.',
            self::SignatoryResolution => 'Board resolution naming who may instruct the firm.',
        };
    }

    /** @return list<self> */
    public static function forType(ClientType $type): array
    {
        return match ($type) {
            ClientType::Individual => [self::IdentityDocument, self::ProofOfAddress],
            ClientType::Entity => [self::RegistrationDocuments, self::ProofOfBusinessAddress, self::DirectorIdentities, self::SignatoryResolution],
        };
    }
}
