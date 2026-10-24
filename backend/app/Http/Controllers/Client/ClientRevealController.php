<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientContactPerson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/*
 * Identifiers and ways of reaching a client are shown masked. Showing one in
 * full is a decision to read it, so it is asked for here, allowed only to those
 * cleared to see unmasked data, and written to the audit log before the value
 * leaves. The log records that it was read and which field, never the value.
 */
class ClientRevealController extends Controller
{
    public function __invoke(Request $request, Client $client): JsonResponse
    {
        Gate::authorize('view', $client);
        Gate::authorize(Permission::ViewUnmaskedClientData->value);

        $target = (string) $request->validate(['target' => ['required', 'string', 'max:100']])['target'];

        [$subject, $field, $value] = $this->resolve($client, $target);

        abort_if($value === null || $value === '', 404);

        $subject->recordAccess('revealed', ['ip' => $request->ip(), 'field' => $field]);

        return response()->json(['value' => $value]);
    }

    /**
     * What is being asked for: "identifier", "date_of_birth", "contact:{id}" or "person:{id}:{mobile|email}".
     *
     * @return array{0: Client|ClientContact|ClientContactPerson, 1: string, 2: ?string}
     */
    protected function resolve(Client $client, string $target): array
    {
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
