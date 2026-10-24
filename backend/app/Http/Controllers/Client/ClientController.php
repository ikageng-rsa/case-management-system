<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Enums\Client\FicaDocument;
use App\Enums\Client\PopiaConsentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use App\Models\Court;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\User;
use App\Services\Clients\AddClientContact;
use App\Services\Clients\DuplicateClientException;
use App\Services\Clients\MaskIdentifier;
use App\Services\Clients\NormaliseIdentifier;
use App\Services\Clients\RecordPopiaConsent;
use App\Services\Clients\RegisterClient;
use App\Services\Clients\UpdateClient;
use App\Services\Matters\OpenMatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

    public function store(
        StoreClientRequest $request,
        RegisterClient $registerClient,
        AddClientContact $addContact,
        RecordPopiaConsent $recordConsent,
        OpenMatter $openMatter,
    ): RedirectResponse {
        Gate::authorize('create', Client::class);

        $opensMatter = $request->opensMatter();

        if ($opensMatter) {
            Gate::authorize('create', Matter::class);
        }

        try {
            [$client, $matter] = DB::transaction(function () use ($request, $registerClient, $addContact, $recordConsent, $openMatter, $opensMatter): array {
                $client = $registerClient->register(ClientType::from($request->validated('type')), $request->validated());

                foreach ($request->contacts() as $kind => $value) {
                    $addContact->add($client, ContactKind::from($kind), $value);
                }

                foreach ($request->ficaFiles() as $document => $file) {
                    $client->addFicaDocument($file, FicaDocument::from($document))->recordAccess('uploaded');
                }

                if ($request->boolean('popia_consent') && $request->user()->can('record_popia_consent')) {
                    $recordConsent->record(
                        $client,
                        true,
                        PopiaConsentMethod::from($request->validated('popia_method')),
                        mandate: $request->file('popia_mandate'),
                    );
                }

                $matter = $opensMatter
                    ? $openMatter->open(
                        $client,
                        MatterType::findOrFail($request->validated('matter_type_id')),
                        court: $request->court(),
                    )
                    : null;

                return [$client, $matter];
            });
        } catch (DuplicateClientException $e) {
            return back()->withInput()->withErrors(['type' => $e->getMessage()], 'client');
        }

        return $matter
            ? redirect()->route('matter.show', $matter)->with('status', 'Client registered and matter opened.')
            : redirect()->route('client.show', $client)->with('status', 'Client registered.');
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        Gate::authorize('update', $client);

        try {
            $updateClient->update($client, $request->validated());
        } catch (DuplicateClientException $e) {
            return back()->withInput()->withErrors(['id_number' => $e->getMessage()], 'client_edit');
        }

        return redirect()
            ->route('client.show', $client)
            ->with('status', 'Client updated.');
    }

    /** Matching clients for the picker in a form, with enough to tell two of the same name apart. */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Client::class);

        return response()->json(
            $this->search($request)
                ->withCount('matters')
                ->limit(8)
                ->get()
                ->map(fn (Client $client) => [
                    'id' => $client->getKey(),
                    'name' => $client->full_name,
                    'detail' => $client->isEntity()
                        ? ($client->registration_number ? 'reg '.MaskIdentifier::registrationNumber($client->registration_number) : '')
                        : ($client->id_number ? ltrim($client->id_type?->prefix().' '.MaskIdentifier::idNumber($client->id_number)) : ''),
                    'matters' => $client->matters_count,
                ]),
        );
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
            'canReveal' => $user->can('view_unmasked_client_data'),
            'canEditClient' => $client !== null && $user->can('update', $client),
            'tab' => $this->tab($request, $user),
            'matterTypes' => $user->can('create', Matter::class) ? MatterType::query()->orderBy('name')->get() : collect(),
            'courts' => $user->can('create', Matter::class) ? Court::query()->orderBy('name')->get() : collect(),
            'attorneys' => $user->can('assign_matters') ? User::query()->orderBy('name')->get() : collect(),
        ] + ($client === null ? [] : $this->detail($client, $user)));
    }

    /** Contacts and contact persons are only for those cleared to see how a client is reached. */
    protected function tab(Request $request, User $user): string
    {
        $tabs = $user->can('view_client_contacts') ? ['overview', 'contacts', 'persons'] : ['overview'];

        return in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'overview';
    }

    /** @return array<string, mixed> */
    protected function detail(Client $client, User $user): array
    {
        $client->load('contacts', 'media');

        $matters = $client->matters()
            ->with('matterType')
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user))
            ->latest('instructed_at')
            ->get();

        $showConsent = $user->can('view_popia_consents');

        // Every decision the client has made, newest first; the first is the one in force.
        $consents = $showConsent
            ? $client->popiaConsents()->with('media')->latest('id')->get()
            : collect();

        return [
            'canDownloadFica' => $user->can('downloadFicaDocument', $client),
            'canDeleteFica' => $user->can('deleteFicaDocument', $client),
            'contactPersons' => $user->can('view_client_contacts') ? $client->contactPersons()->orderByDesc('is_primary')->oldest()->get() : collect(),
            'matters' => $matters,
            'openMattersCount' => $matters->filter->isOpen()->count(),
            'consent' => $consents->first(),
            'consents' => $consents,
            'showConsent' => $showConsent,
            'canRecordConsent' => $user->can('record_popia_consent'),
            'canWithdrawConsent' => $user->can('withdraw_popia_consent'),
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
