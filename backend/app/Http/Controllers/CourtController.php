<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Auth\Permission;
use App\Http\Requests\Reference\SaveCourtRequest;
use App\Models\Court;
use App\Services\Courts\RetireCourt;
use App\Services\Courts\SaveCourt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CourtController extends Controller
{
    public function store(SaveCourtRequest $request, SaveCourt $saveCourt): RedirectResponse
    {
        Gate::authorize(Permission::ManageCourts->value);

        $saveCourt->save(null, $request->validated());

        return $this->back()->with('status', 'Court added.');
    }

    public function update(SaveCourtRequest $request, Court $court, SaveCourt $saveCourt): RedirectResponse
    {
        Gate::authorize(Permission::ManageCourts->value);

        $saveCourt->save($court, $request->validated());

        return $this->back()->with('status', 'Court updated.');
    }

    public function destroy(Court $court, RetireCourt $retireCourt): RedirectResponse
    {
        Gate::authorize(Permission::ManageCourts->value);

        $outcome = $retireCourt->remove($court);

        return $this->back()->with('status', $outcome === 'retired'
            ? 'Court retired. It is no longer offered, and files that carry it still read correctly.'
            : 'Court deleted.');
    }

    public function restore(Court $court, RetireCourt $retireCourt): RedirectResponse
    {
        Gate::authorize(Permission::ManageCourts->value);

        $retireCourt->restore($court);

        return $this->back()->with('status', 'Court restored.');
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('reference-data', ['tab' => 'courts']);
    }
}
