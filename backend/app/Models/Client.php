<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Client\EncryptedDate;
use App\Casts\Client\IdNumber;
use App\Casts\Client\RegistrationNumber;
use App\Concerns\RecordsActivity;
use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Enums\Client\FicaDocument;
use App\Enums\Client\IdentityType;
use App\Models\Matter\Matter;
use App\Observers\ClientObserver;
use App\Support\Clients\GenerateBlindIndex;
use App\Support\Clients\NormaliseIdentifier;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ObservedBy([ClientObserver::class])]
#[Fillable(['type', 'first_name', 'last_name', 'entity_name', 'id_type', 'id_number', 'registration_number', 'date_of_birth'])]
#[Hidden(['id_number', 'id_number_hash', 'date_of_birth'])]
class Client extends Model implements HasMedia
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;
    use RecordsActivity;
    use SoftDeletes;

    /** The FICA documents on file, each with the kind of document kept as a custom property. */
    public const FICA = 'fica';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'id_type' => IdentityType::class,
            'id_number' => IdNumber::class,
            'date_of_birth' => EncryptedDate::class,
            'registration_number' => RegistrationNumber::class,
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /** The people to call at an entity, the first contact then alternatives. */
    public function contactPersons(): HasMany
    {
        return $this->hasMany(ClientContactPerson::class);
    }

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }

    /** The most recent POPIA consent decision recorded for this client. */
    public function popiaConsent(): HasOne
    {
        return $this->hasOne(PopiaConsent::class)->latestOfMany();
    }

    public function popiaConsents(): HasMany
    {
        return $this->hasMany(PopiaConsent::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::FICA)
            ->useDisk(config('media-library.disk_name'));
    }

    /** @return MediaCollection<int, Media> */
    public function ficaDocuments(?FicaDocument $document = null): MediaCollection
    {
        return $this->getMedia(
            static::FICA,
            fn (Media $media) => $document === null || $media->getCustomProperty('document') === $document->value,
        );
    }

    public function addFicaDocument(string|UploadedFile $file, FicaDocument $document): Media
    {
        return $this->addMedia($file)
            ->withCustomProperties(['document' => $document->value])
            ->toMediaCollection(static::FICA);
    }

    /**
     * What FICA asks for from this kind of client that is not yet on file.
     *
     * @return list<FicaDocument>
     */
    public function outstandingFicaDocuments(): array
    {
        return array_values(array_filter(
            FicaDocument::forType($this->type),
            fn (FicaDocument $document) => $this->ficaDocuments($document)->isEmpty(),
        ));
    }

    /** Individuals are named first/last; entities carry a single registered name. */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->type === ClientType::Entity) {
                return (string) $this->entity_name;
            }

            return trim("{$this->first_name} {$this->last_name}");
        });
    }

    /** Extract initials formed from first and last name */
    protected function initials(): Attribute
    {
        return Attribute::get(
            fn () => Str::initials($this->full_name, capitalize: true)
        );
    }

    public function isEntity(): bool
    {
        return $this->type === ClientType::Entity;
    }

    public function hasGrantedPopiaConsent(): bool
    {
        return (bool) $this->popiaConsent?->isActive();
    }

    public function primaryContact(ContactKind $kind): ?ClientContact
    {
        return $this->contacts
            ->where('kind', $kind)
            ->firstWhere('is_primary', true);
    }

    /**
     * Look a client up by ID or passport number without decrypting the column. Given a
     * type, only numbers of that kind match; without one, a number of any kind does.
     */
    public function scopeMatchingIdNumber(Builder $query, string $idNumber, ?IdentityType $type = null): void
    {
        $hashes = collect($type === null ? IdentityType::cases() : [$type])
            ->map(fn (IdentityType $kind) => [$kind, NormaliseIdentifier::identity($kind, $idNumber)])
            ->reject(fn (array $pair) => $pair[1] === '')
            ->map(fn (array $pair) => GenerateBlindIndex::of(NormaliseIdentifier::identityIndexKey($pair[0], $pair[1])))
            ->all();

        // Nothing to match on is a match for nothing, not for every client without a number.
        $query->whereIn('id_number_hash', $hashes);
    }

    /** Look a client up by registration number in its canonical form. */
    public function scopeMatchingRegistrationNumber(Builder $query, string $registrationNumber): void
    {
        $query->where('registration_number', NormaliseIdentifier::registrationNumber($registrationNumber));
    }

    public function scopeOfType(Builder $query, ClientType $type): void
    {
        $query->where('type', $type);
    }

    public function auditLabel(): string
    {
        return 'client '.$this->full_name;
    }

    /**
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['id_number', 'id_number_hash', 'date_of_birth'];
    }
}
