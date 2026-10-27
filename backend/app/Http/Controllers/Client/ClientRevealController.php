<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RevealClientFieldRequest;
use App\Models\Client\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/*
 * Identifiers and ways of reaching a client are shown masked. Showing one in
 * full is a decision to read it, so it is asked for here, allowed only to those
 * cleared to see unmasked data, and written to the audit log before the value
 * leaves. The log records that it was read and which field, never the value.
 */
class ClientRevealController extends Controller
{
    public function __invoke(RevealClientFieldRequest $request, Client $client): JsonResponse
    {
        Gate::authorize('view', $client);
        Gate::authorize(Permission::ViewUnmaskedClientData->value);

        [$subject, $field, $value] = $request->field($client);

        abort_if($value === null || $value === '', 404);

        $subject->recordAccess('revealed', ['ip' => $request->ip(), 'field' => $field]);

        return response()->json(['value' => $value]);
    }
}
