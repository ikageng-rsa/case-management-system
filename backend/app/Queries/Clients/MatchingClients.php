<?php

declare(strict_types=1);

namespace App\Queries\Clients;

use App\Enums\Client\ContactKind;
use App\Models\Client\Client;
use App\Support\Clients\NormaliseIdentifier;
use Illuminate\Database\Eloquent\Builder;

/*
 * The clients a search finds, by name, ID or registration number, or a mobile
 * or email on file, in the order of their names. Left as a query to be paged or
 * cut short by whoever asks.
 */
class MatchingClients
{
    /** @return Builder<Client> */
    public function for(string $term): Builder
    {
        $term = trim($term);

        return Client::query()
            ->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('entity_name', 'like', $like)
                    // Text with no digits normalises to nothing, and nothing hashes to NULL, which every entity has.
                    ->when(NormaliseIdentifier::idNumber($term) !== '', fn (Builder $query) => $query
                        ->orWhere(fn (Builder $query) => $query->matchingIdNumber($term)))
                    ->orWhere(fn (Builder $query) => $query->matchingRegistrationNumber($term))
                    ->orWhereHas('contacts', fn (Builder $query) => $query->matchingValue($term, ContactKind::Mobile))
                    ->orWhereHas('contacts', fn (Builder $query) => $query->matchingValue($term, ContactKind::Email));
            }))
            ->orderByRaw('coalesce(entity_name, last_name)')
            ->orderBy('first_name');
    }
}
