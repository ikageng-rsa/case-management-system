<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\ListClientsRequest;
use App\Http\Resources\Client\ClientResource;
use App\Models\Client\Client;
use App\Queries\Clients\MatchingClients;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(ListClientsRequest $request, MatchingClients $matchingClients): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);

        return ClientResource::collection(
            $matchingClients->for($request->search())
                ->withCount('matters')
                ->paginate(20)
                ->withQueryString(),
        );
    }

    /** A client the user may not see is not found, so the app cannot learn that one exists. */
    public function show(Request $request, Client $client): ClientResource
    {
        abort_unless(Gate::allows('view', $client), 404);

        if ($request->user()->can(Permission::ViewClientContacts->value)) {
            $client->load([
                'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
                'contactPersons' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
            ]);
        }

        $client->loadCount('matters');
        $client->recordAccess('read', ['ip' => $request->ip(), 'via' => 'api']);

        return new ClientResource($client);
    }
}
