<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SaveBillingSettingsRequest;
use App\Services\Billing\SaveBillingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BillingSettingsController extends Controller
{
    public function update(SaveBillingSettingsRequest $request, SaveBillingSettings $saveSettings): RedirectResponse
    {
        Gate::authorize(Permission::ManageBillingSettings->value);

        $saveSettings->save($request->settings());

        return redirect()->route('settings', ['tab' => 'billing'])->with('status', 'Billing settings saved.');
    }
}
