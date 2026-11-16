<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Enums\Billing\TariffAdjustmentKind;
use App\Enums\Billing\TariffScale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreTariffScheduleRequest;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\User\Role;
use App\Services\Billing\ImportTariffSchedule;
use App\Services\Billing\RemoveTariffSchedule;
use App\Services\Billing\TariffImportFailed;
use App\Services\Billing\TariffScheduleHasStarted;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with('status', "Read {$schedule->items()->count()} lines. Check the rates and markups, then publish.");
    }

    /*
     * The tariff as it was read, with the markup each role charges against it.
     * The rates can be seen as any role would charge them, to check a markup
     * before the tariff is published.
     */
    public function show(Request $request, TariffSchedule $tariffSchedule): View
    {
        Gate::authorize(Permission::ManageTariffs->value);

        $tariffSchedule->load(['items.activityType', 'adjustments']);
        $roles = Role::query()->orderBy('name')->get();

        return view('tariff-schedule', [
            'schedule' => $tariffSchedule,
            'items' => $tariffSchedule->items->sortBy(fn (TariffItem $item) => $item->activityType->name)->values(),
            'roles' => $roles,
            'asRole' => $roles->firstWhere('id', (int) $request->query('role')),
            'status' => $tariffSchedule->status(TariffSchedule::inForceOn(today())),
            'scales' => TariffScale::cases(),
            'kinds' => TariffAdjustmentKind::cases(),
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
