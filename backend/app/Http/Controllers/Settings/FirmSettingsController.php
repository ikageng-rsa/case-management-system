<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveFirmSettingsRequest;
use App\Services\Settings\SaveFirmSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FirmSettingsController extends Controller
{
    public function update(SaveFirmSettingsRequest $request, SaveFirmSettings $saveSettings): RedirectResponse
    {
        Gate::authorize(Permission::ManageFirmSettings->value);

        $saveSettings->save($request->settings());

        return redirect()->route('settings', ['tab' => 'firm'])->with('status', 'Firm details saved.');
    }
}
