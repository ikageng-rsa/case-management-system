<?php

namespace App\Models;

use App\Enums\Client\ContactKind;
use App\Services\Clients\GenerateBlindIndex;
use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kind', 'value', 'is_primary'])]
#[Hidden(['value_hash'])]
class ClientContact extends Model
{
    /** @use HasFactory<ClientContactFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (ClientContact $contact) {
            if (! $contact->isDirty('value')) {
                return;
            }

            $contact->value_hash = GenerateBlindIndex::of($contact->value);
        });

        /*
         * A client has at most one primary contact per kind, so promoting one
         * demotes whichever sibling of the same kind currently holds the flag.
         */
        static::saved(function (ClientContact $contact) {
            if (! $contact->is_primary) {
                return;
            }

            static::query()
                ->where('client_id', $contact->client_id)
                ->where('kind', $contact->kind)
                ->whereKeyNot($contact->getKey())
                ->update(['is_primary' => false]);
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
            'kind' => ContactKind::class,
            'value' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Look a contact up by its value without decrypting the column. */
    public function scopeMatchingValue(Builder $query, string $value): void
    {
        $query->where('value_hash', GenerateBlindIndex::of($value));
    }

    public function scopeOfKind(Builder $query, ContactKind $kind): void
    {
        $query->where('kind', $kind);
    }

    public function scopePrimary(Builder $query): void
    {
        $query->where('is_primary', true);
    }
}
