<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Enums\Billing\BillingTiming;
use App\Enums\Billing\DrawingFeeBase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SaveBillingSettingsRequest;
use App\Services\Billing\SaveBillingSettings;
use App\Settings\BillingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BillingSettingsController extends Controller
{
    public function show(BillingSettings $settings): View
    {
        Gate::authorize(Permission::ManageBillingSettings->value);

        return view('billing-settings', [
            'settings' => $settings,
            'bases' => DrawingFeeBase::cases(),
            'timings' => BillingTiming::cases(),
        ]);
    }

    public function update(SaveBillingSettingsRequest $request, SaveBillingSettings $saveSettings): RedirectResponse
    {
        Gate::authorize(Permission::ManageBillingSettings->value);

        $saveSettings->save($request->settings());

        return redirect()->route('billing-settings')->with('status', 'Billing settings saved.');
    }
}
