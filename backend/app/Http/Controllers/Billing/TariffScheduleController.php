<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreTariffScheduleRequest;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Services\Billing\ImportTariffSchedule;
use App\Services\Billing\RemoveTariffSchedule;
use App\Services\Billing\TariffImportFailed;
use App\Services\Billing\TariffScheduleHasStarted;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TariffScheduleController extends Controller
{
    public function store(StoreTariffScheduleRequest $request, ImportTariffSchedule $importSchedule): RedirectResponse
    {
        Gate::authorize(Permission::ManageTariffs->value);

        try {
            $schedule = $importSchedule->import($request->validated('name'), $request->effectiveFrom(), $request->file('file'));
        } catch (TariffImportFailed $e) {
            return $this->back()->withInput()->withErrors(['file' => $e->problems], 'tariff_schedule_new');
        }

        return redirect()->route('tariff-schedule.show', $schedule)
            ->with('status', "Tariff uploaded with {$schedule->items()->count()} rates.");
    }

    public function show(TariffSchedule $tariffSchedule): View
    {
        Gate::authorize(Permission::ManageTariffs->value);

        $items = $tariffSchedule->items()->with(['activityType', 'role'])->get()
            ->sortBy(fn (TariffItem $item) => [$item->activityType->name, $item->scale?->value ?? '', $item->role?->name ?? ''])
            ->values();

        return view('tariff-schedule', [
            'schedule' => $tariffSchedule,
            'items' => $items,
            'inForce' => TariffSchedule::inForceOn(today())?->is($tariffSchedule) ?? false,
        ]);
    }

    public function destroy(TariffSchedule $tariffSchedule, RemoveTariffSchedule $removeSchedule): RedirectResponse
    {
        Gate::authorize(Permission::ManageTariffs->value);

        try {
            $removeSchedule->remove($tariffSchedule);
        } catch (TariffScheduleHasStarted $e) {
            return $this->back()->withErrors(['tariff_schedule' => $e->getMessage()], 'tariff_schedules');
        }

        return $this->back()->with('status', 'Tariff removed.');
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('reference-data', ['tab' => 'tariffs']);
    }
}
