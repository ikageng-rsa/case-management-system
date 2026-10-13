<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Client\ContactKind;
use App\Http\Requests\Client\StoreClientContactRequest;
use App\Http\Requests\Client\UpdateClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\Clients\AddClientContact;
use App\Services\Clients\CannotRemoveLastContact;
use App\Services\Clients\ContactKindLimitException;
use App\Services\Clients\RemoveClientContact;
use App\Services\Clients\UpdateClientContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ClientContactController extends Controller
{
    public function store(StoreClientContactRequest $request, Client $client, AddClientContact $addContact): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view_client_contacts');

        try {
            $addContact->add(
                $client,
                ContactKind::from($request->validated('kind')),
                $request->validated('value'),
                $request->boolean('is_primary'),
            );
        } catch (ContactKindLimitException $e) {
            return back()->withInput()->withErrors(['kind' => $e->getMessage()], 'contact_new');
        }

        return $this->back($client)->with('status', 'Contact added.');
    }

    public function update(UpdateClientContactRequest $request, Client $client, ClientContact $contact, UpdateClientContact $updateContact): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view_client_contacts');
        abort_unless($contact->client_id === $client->getKey(), 404);

        $updateContact->update($contact, $request->validated('value'), $request->boolean('is_primary'));

        return $this->back($client)->with('status', 'Contact updated.');
    }

    public function destroy(Client $client, ClientContact $contact, RemoveClientContact $removeContact): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view_client_contacts');
        abort_unless($contact->client_id === $client->getKey(), 404);

        try {
            $removeContact->remove($contact);
        } catch (CannotRemoveLastContact $e) {
            return $this->back($client)->withErrors(['contact' => $e->getMessage()], 'contacts');
        }

        return $this->back($client)->with('status', 'Contact removed.');
    }

    protected function back(Client $client): RedirectResponse
    {
        return redirect()->route('client.show', [$client, 'tab' => 'contacts']);
    }
}
