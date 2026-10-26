<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RevealClientFieldRequest;
use App\Http\Resources\Client\RevealedFieldResource;
use App\Models\Client\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RevealClientFieldController extends Controller
{
    /**
     * One masked field in full, as on the web: only to those cleared to see unmasked
     * data, and logged, without the value, before it leaves. A client the user may
     * not see is not found.
     */
    public function __invoke(RevealClientFieldRequest $request, Client $client): JsonResponse
    {
        abort_unless(Gate::allows('view', $client), 404);
        Gate::authorize(Permission::ViewUnmaskedClientData->value);

        [$subject, $field, $value] = $request->field($client);

        abort_if($value === null || $value === '', 404);

        $subject->recordAccess('revealed', ['ip' => $request->ip(), 'field' => $field, 'via' => 'api']);

        return (new RevealedFieldResource([
            'target' => (string) $request->validated('target'),
            'field' => $field,
            'value' => $value,
        ]))->response()->header('Cache-Control', 'no-store');
    }
}
