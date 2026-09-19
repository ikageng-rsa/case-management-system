<?php

namespace App\Models;

use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Services\Clients\GenerateBlindIndex;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['type', 'first_name', 'last_name', 'entity_name', 'id_number', 'registration_number'])]
#[Hidden(['id_number', 'id_number_hash'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            if (! $client->isDirty('id_number')) {
                return;
            }

            $client->id_number_hash = GenerateBlindIndex::of($client->id_number);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'id_number' => 'encrypted',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
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

    /** Look a client up by ID number without decrypting the column. */
    public function scopeMatchingIdNumber(Builder $query, string $idNumber): void
    {
        $query->where('id_number_hash', GenerateBlindIndex::of($idNumber));
    }

    public function scopeOfType(Builder $query, ClientType $type): void
    {
        $query->where('type', $type);
    }
}
