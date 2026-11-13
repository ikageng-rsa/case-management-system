<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SaveTariffAdjustmentsRequest;
use App\Models\Billing\TariffSchedule;
use App\Services\Billing\SaveTariffAdjustments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TariffAdjustmentsController extends Controller
{
    public function update(SaveTariffAdjustmentsRequest $request, TariffSchedule $tariffSchedule, SaveTariffAdjustments $saveAdjustments): RedirectResponse
    {
        Gate::authorize(Permission::ManageTariffs->value);

        $saveAdjustments->save($tariffSchedule, $request->adjustments());

        return redirect()->route('tariff-schedule.show', $tariffSchedule)->with('status', 'Markups saved.');
    }
}
