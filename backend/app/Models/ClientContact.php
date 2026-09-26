<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Client\ContactValue;
use App\Concerns\RecordsActivity;
use App\Enums\Client\ContactKind;
use App\Observers\ClientContactObserver;
use App\Services\Clients\GenerateBlindIndex;
use App\Services\Clients\NormaliseIdentifier;
use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ClientContactObserver::class])]
#[Fillable(['kind', 'value', 'is_primary'])]
#[Hidden(['value_hash'])]
class ClientContact extends Model
{
    /** @use HasFactory<ClientContactFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ContactKind::class,
            'value' => ContactValue::class,
            'is_primary' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Look a contact up by its value without decrypting the column. */
    public function scopeMatchingValue(Builder $query, string $value, ?ContactKind $kind = null): void
    {
        $value = $kind !== null
        ? NormaliseIdentifier::contact($kind, $value)
        : mb_strtolower(trim($value));

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

    public function auditLabel(): string
    {
        return $this->kind->value.' contact for client '.$this->client?->full_name;
    }

    /**
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['value', 'value_hash'];
    }
}
