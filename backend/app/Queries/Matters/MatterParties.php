<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Enums\Matter\PartyRole;
use App\Models\Matter\Matter;
use App\Models\User;

/*
 * Everyone in a matter besides the firm, in the order they were added, and the
 * client's contacts for those allowed to see them.
 */
class MatterParties
{
    /** @return array<string, mixed> */
    public function for(Matter $matter, User $user): array
    {
        return [
            'matterParties' => $matter->parties()->orderBy('created_at')->orderBy('id')->get(),
            'partyRoles' => PartyRole::cases(),
            'contacts' => $user->can('view_client_contacts') ? $matter->client->contacts()->get() : collect(),
            'canReveal' => $user->can('view_unmasked_client_data'),
        ];
    }
}
