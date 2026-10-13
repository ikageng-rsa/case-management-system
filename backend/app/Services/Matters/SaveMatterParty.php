<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Enums\Matter\PartyRole;
use App\Models\Matter;
use App\Models\MatterParty;

class SaveMatterParty
{
    /**
     * Add a party to the matter, or change one already on it. A closed matter is
     * a finished record, so it takes no new parties and its parties stay as they were.
     *
     * @param  array{name: string, role: string, representative?: ?string}  $data
     */
    public function save(Matter $matter, ?MatterParty $party, array $data): MatterParty
    {
        if ($matter->isClosed()) {
            throw new CannotChangePartiesOfClosedMatter($matter);
        }

        $party ??= new MatterParty;

        $party->fill([
            'name' => trim($data['name']),
            'role' => PartyRole::from($data['role']),
            'representative' => filled($data['representative'] ?? null) ? trim($data['representative']) : null,
        ]);

        $party->matter()->associate($matter);
        $party->save();

        return $party;
    }
}
