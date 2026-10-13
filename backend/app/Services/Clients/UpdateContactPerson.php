<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ContactKind;
use App\Models\ClientContactPerson;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateContactPerson
{
    /**
     * An empty mobile or email keeps what is on file, which is how a person is
     * edited without their details being shown in full. As with contacts, the
     * first-contact flag moves by naming someone else, never by switching it off.
     *
     * @param  array{name: string, role?: ?string, mobile?: ?string, email?: ?string}  $data
     */
    public function update(ClientContactPerson $person, array $data, bool $isPrimary = false): ClientContactPerson
    {
        return DB::transaction(function () use ($person, $data, $isPrimary): ClientContactPerson {
            $person->name = trim($data['name']);
            $person->role = filled($data['role'] ?? null) ? trim($data['role']) : null;

            $changed = [];

            foreach (['mobile' => ContactKind::Mobile, 'email' => ContactKind::Email] as $field => $kind) {
                $value = $data[$field] ?? null;

                if (filled($value) && NormaliseIdentifier::contact($kind, $value) !== $person->{$field}) {
                    $person->{$field} = $value;
                    $changed[] = $field;
                }
            }

            if ($isPrimary && ! $person->is_primary) {
                $person->client->contactPersons()->whereKeyNot($person->getKey())->update(['is_primary' => false]);
                $person->is_primary = true;
            }

            $person->save();

            // Mobile and email are kept out of the activity log, so a change to
            // either would otherwise leave no trace at all.
            if ($changed !== []) {
                $person->recordEvent(
                    'value_changed',
                    sprintf('%s changed the %s of contact person %s', Auth::user()?->name ?? 'System', implode(' and ', $changed), $person->name),
                    ['field' => implode(',', $changed)],
                );
            }

            return $person;
        });
    }
}
