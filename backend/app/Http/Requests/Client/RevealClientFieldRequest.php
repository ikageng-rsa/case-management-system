<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Auth\Permission;
use App\Models\Client\Client;
use App\Models\Client\ClientContact;
use App\Models\Client\ClientContactPerson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/*
 * Which masked field of a client is asked for in full. The web and the app ask
 * the same way, so a reveal is resolved, and refused, the same way on both.
 */
class RevealClientFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * What is being asked for: "identifier", "date_of_birth", "contact:{id}" or "person:{id}:{mobile|email}".
     *
     * @return array{0: Client|ClientContact|ClientContactPerson, 1: string, 2: ?string}
     */
    public function field(Client $client): array
    {
        $target = (string) $this->validated('target');

        if ($target === 'identifier') {
            return $client->isEntity()
                ? [$client, 'registration_number', $client->registration_number]
                : [$client, 'id_number', $client->id_number];
        }

        if ($target === 'date_of_birth') {
            return [$client, 'date_of_birth', $client->date_of_birth?->format('d M Y')];
        }

        Gate::authorize(Permission::ViewClientContacts->value);

        if (preg_match('/^contact:(\d+)$/', $target, $matches) === 1) {
            $contact = $client->contacts()->findOrFail((int) $matches[1]);

            return [$contact, $contact->kind->value, $contact->value];
        }

        if (preg_match('/^person:([0-9a-f-]{36}):(mobile|email)$/', $target, $matches) === 1) {
            $person = $client->contactPersons()->findOrFail($matches[1]);

            return [$person, $matches[2], $person->{$matches[2]}];
        }

        abort(404);
    }
}
