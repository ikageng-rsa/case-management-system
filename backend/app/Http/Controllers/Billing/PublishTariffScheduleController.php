<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Models\Billing\TariffSchedule;
use App\Services\Billing\PublishTariffSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PublishTariffScheduleController extends Controller
{
    public function __invoke(TariffSchedule $tariffSchedule, PublishTariffSchedule $publishSchedule): RedirectResponse
    {
        Gate::authorize(Permission::ManageTariffs->value);

        $publishSchedule->publish($tariffSchedule);

        return redirect()->route('tariff-schedule.show', $tariffSchedule)
            ->with('status', "Tariff published. Bills from {$tariffSchedule->effective_from->format('j M Y')} are priced from it.");
    }
}
