<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reference;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\SaveActivityTypeRequest;
use App\Models\Reference\ActivityType;
use App\Services\ActivityTypes\RetireActivityType;
use App\Services\ActivityTypes\SaveActivityType;
use App\Services\Reference\CannotRemoveSystemRecord;
use App\Services\Reference\ReferenceDataLocked;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ActivityTypeController extends Controller
{
    public function store(SaveActivityTypeRequest $request, SaveActivityType $saveType): RedirectResponse
    {
        Gate::authorize(Permission::ManageActivityTypes->value);

        $saveType->save(null, $request->validated());

        return $this->back()->with('status', 'Kind of work added.');
    }

    public function update(SaveActivityTypeRequest $request, ActivityType $activityType, SaveActivityType $saveType): RedirectResponse
    {
        Gate::authorize(Permission::ManageActivityTypes->value);

        try {
            $saveType->save($activityType, $request->validated());
        } catch (ReferenceDataLocked $e) {
            return $this->back()->withInput()->withErrors(['measure' => $e->getMessage()], 'activity_type_'.$activityType->getKey());
        }

        return $this->back()->with('status', 'Kind of work updated.');
    }

    public function destroy(ActivityType $activityType, RetireActivityType $retireType): RedirectResponse
    {
        Gate::authorize(Permission::ManageActivityTypes->value);

        try {
            $outcome = $retireType->remove($activityType);
        } catch (CannotRemoveSystemRecord $e) {
            return $this->back()->withErrors(['activity_type' => $e->getMessage()], 'activity_types');
        }

        return $this->back()->with('status', $outcome === 'retired'
            ? 'Kind of work retired. It is no longer offered, and its narrations still read correctly.'
            : 'Kind of work deleted.');
    }

    public function restore(ActivityType $activityType, RetireActivityType $retireType): RedirectResponse
    {
        Gate::authorize(Permission::ManageActivityTypes->value);

        $retireType->restore($activityType);

        return $this->back()->with('status', 'Kind of work restored.');
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('reference-data', ['tab' => 'activity-types']);
    }
}
