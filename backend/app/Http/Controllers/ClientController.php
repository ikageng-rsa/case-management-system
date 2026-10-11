<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Http\Requests\Client\StoreClientRequest;
use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use App\Services\Clients\AddClientContact;
use App\Services\Clients\DuplicateClientException;
use App\Services\Clients\NormaliseIdentifier;
use App\Services\Clients\RegisterClient;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', Client::class);

        $first = $this->search($request)->first();

        // The page is a list beside the selected client, so land on the first one.
        if ($first !== null) {
            return redirect()->route('client.show', array_filter(['client' => $first, 'q' => $request->query('q')]));
        }

        return $this->render($request, null);
    }

    public function show(Request $request, Client $client): View
    {
        Gate::authorize('view', $client);

        $client->recordAccess('read', ['ip' => $request->ip()]);

        return $this->render($request, $client);
    }

    public function store(StoreClientRequest $request, RegisterClient $registerClient, AddClientContact $addContact): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        try {
            $client = DB::transaction(function () use ($request, $registerClient, $addContact): Client {
                $client = $registerClient->register(ClientType::from($request->validated('type')), $request->validated());

                foreach (['mobile' => ContactKind::Mobile, 'email' => ContactKind::Email] as $field => $kind) {
                    if ($request->filled($field)) {
                        $addContact->add($client, $kind, $request->validated($field));
                    }
                }

                return $client;
            });
        } catch (DuplicateClientException $e) {
            return back()->withInput()->withErrors(['type' => $e->getMessage()], 'client');
        }

        return redirect()
            ->route('client.show', $client)
            ->with('status', 'Client registered.');
    }

    protected function render(Request $request, ?Client $client): View
    {
        /** @var User $user */
        $user = $request->user();

        $clients = $this->search($request)
            ->with('contacts')
            ->withCount('matters')
            ->paginate(20)
            ->withQueryString();

        return view('clients', [
            'clients' => $clients,
            'total' => Client::query()->count(),
            'query' => (string) $request->query('q'),
            'client' => $client,
            'canSeeContacts' => $user->can('view_client_contacts'),
        ] + ($client === null ? [] : $this->detail($client, $user)));
    }

    /** @return array<string, mixed> */
    protected function detail(Client $client, User $user): array
    {
        $client->load('contacts');

        $matters = $client->matters()
            ->with('matterType')
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user))
            ->latest('instructed_at')
            ->get();

        $consent = $user->can('view_popia_consents')
            ? $client->popiaConsent()->first()
            : null;

        return [
            'matters' => $matters,
            'openMattersCount' => $matters->filter->isOpen()->count(),
            'consent' => $consent,
            'showConsent' => $user->can('view_popia_consents'),
        ];
    }

    /**
     * Names match as a fragment. Identifiers are stored encrypted and found
     * through a blind index, so those only match whole.
     *
     * @return Builder<Client>
     */
    protected function search(Request $request): Builder
    {
        $term = trim((string) $request->query('q'));

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
