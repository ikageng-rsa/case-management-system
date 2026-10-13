<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Auth\Permission;
use App\Enums\Client\PopiaConsentMethod;
use App\Http\Requests\Client\StorePopiaConsentRequest;
use App\Models\Client;
use App\Models\PopiaConsent;
use App\Services\Clients\RecordPopiaConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PopiaConsentController extends Controller
{
    /*
     * Consent is only ever granted by recording it, with its method and evidence,
     * and only ever ended by withdrawing it. A withdrawal is final for that
     * consent: giving consent again is a new decision, recorded in its own right,
     * so the history of what the client agreed to and when is never rewritten.
     */
    public function store(StorePopiaConsentRequest $request, Client $client, RecordPopiaConsent $recordConsent): RedirectResponse
    {
        Gate::authorize(Permission::RecordPopiaConsent->value);
        Gate::authorize('view', $client);

        if ($client->popiaConsent()->first()?->isActive()) {
            return $this->back($client)->withErrors(['method' => 'This client has already given consent.'], 'popia');
        }

        $consentedOn = $request->date('consented_on');

        $recordConsent->record(
            $client,
            true,
            PopiaConsentMethod::from($request->validated('method')),
            // A date picked without a time is taken to be that day, at this hour.
            $consentedOn?->setTimeFrom(now()),
            $request->file('mandate'),
        );

        return $this->back($client)->with('status', 'Consent recorded.');
    }

    public function withdraw(Client $client, RecordPopiaConsent $recordConsent): RedirectResponse
    {
        Gate::authorize(Permission::WithdrawPopiaConsent->value);
        Gate::authorize('view', $client);

        $current = $client->popiaConsent()->first();

        if (! $current?->isActive()) {
            return $this->back($client)->withErrors(['consent' => 'There is no active consent to withdraw.'], 'popia');
        }

        $recordConsent->record($client, false, $current->method);

        return $this->back($client)->with('status', 'Consent withdrawn.');
    }

    public function mandate(Request $request, Client $client, PopiaConsent $popiaConsent): Response
    {
        Gate::authorize(Permission::ViewPopiaConsents->value);
        Gate::authorize('view', $client);

        abort_unless($popiaConsent->client_id === $client->getKey(), 404);

        $mandate = $popiaConsent->mandate();

        abort_if($mandate === null, 404);

        $mandate->recordAccess('downloaded');

        return $mandate->toResponse($request);
    }

    protected function back(Client $client): RedirectResponse
    {
        return redirect()->route('client.show', $client);
    }
}
