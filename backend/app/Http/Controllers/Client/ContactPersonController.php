<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SaveContactPersonRequest;
use App\Models\Client;
use App\Models\ClientContactPerson;
use App\Services\Clients\AddContactPerson;
use App\Services\Clients\RemoveContactPerson;
use App\Services\Clients\UpdateContactPerson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ContactPersonController extends Controller
{
    public function store(SaveContactPersonRequest $request, Client $client, AddContactPerson $addPerson): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view_client_contacts');

        $addPerson->add($client, $request->validated(), $request->boolean('is_primary'));

        return $this->back($client)->with('status', 'Contact person added.');
    }

    public function update(SaveContactPersonRequest $request, Client $client, ClientContactPerson $person, UpdateContactPerson $updatePerson): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view_client_contacts');
        abort_unless($person->client_id === $client->getKey(), 404);

        $updatePerson->update($person, $request->validated(), $request->boolean('is_primary'));

        return $this->back($client)->with('status', 'Contact person updated.');
    }

    public function destroy(Client $client, ClientContactPerson $person, RemoveContactPerson $removePerson): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view_client_contacts');
        abort_unless($person->client_id === $client->getKey(), 404);

        $removePerson->remove($person);

        return $this->back($client)->with('status', 'Contact person removed.');
    }

    protected function back(Client $client): RedirectResponse
    {
        return redirect()->route('client.show', [$client, 'tab' => 'persons']);
    }
}
