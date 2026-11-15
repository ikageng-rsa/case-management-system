<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveInvoiceSettingsRequest;
use App\Services\Settings\SaveInvoiceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InvoiceSettingsController extends Controller
{
    public function update(SaveInvoiceSettingsRequest $request, SaveInvoiceSettings $saveSettings): RedirectResponse
    {
        Gate::authorize(Permission::ManageBillingSettings->value);

        $saveSettings->save($request->settings());

        return redirect()->route('settings', ['tab' => 'invoices'])->with('status', 'Invoice settings saved.');
    }
}
