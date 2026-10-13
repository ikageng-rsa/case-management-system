<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\ClientContact;
use Illuminate\Support\Facades\Auth;

class UpdateClientContact
{
    /**
     * A contact keeps its kind. An empty value keeps the current one, which is
     * how a contact is edited without being shown in full. Being the primary of
     * its kind is given up by promoting another, never by switching it off, so a
     * kind never ends up with no primary; the observer demotes the previous one.
     */
    public function update(ClientContact $contact, ?string $value, bool $isPrimary = false): ClientContact
    {
        $valueChanged = filled($value) && NormaliseIdentifier::contact($contact->kind, $value) !== $contact->value;

        if ($valueChanged) {
            $contact->value = $value;
        }

        if ($isPrimary) {
            $contact->is_primary = true;
        }

        $contact->save();

        // The value is kept out of the activity log, so a change to it would
        // otherwise leave no trace at all.
        if ($valueChanged) {
            $contact->recordEvent(
                'value_changed',
                sprintf('%s changed the %s of client %s', Auth::user()?->name ?? 'System', str_replace('-', ' ', $contact->kind->value), $contact->client->full_name),
                ['field' => $contact->kind->value],
            );
        }

        return $contact;
    }
}
