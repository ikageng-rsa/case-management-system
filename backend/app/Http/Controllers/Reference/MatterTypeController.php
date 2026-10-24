<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reference;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\SaveMatterTypeRequest;
use App\Models\MatterType;
use App\Services\MatterTypes\RetireMatterType;
use App\Services\MatterTypes\SaveMatterType;
use App\Services\Reference\ReferenceDataLocked;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MatterTypeController extends Controller
{
    public function store(SaveMatterTypeRequest $request, SaveMatterType $saveType): RedirectResponse
    {
        Gate::authorize(Permission::ManageMatterTypes->value);

        $saveType->save(null, $request->validated());

        return $this->back()->with('status', 'Matter type added.');
    }

    public function update(SaveMatterTypeRequest $request, MatterType $matterType, SaveMatterType $saveType): RedirectResponse
    {
        Gate::authorize(Permission::ManageMatterTypes->value);

        try {
            $saveType->save($matterType, $request->validated());
        } catch (ReferenceDataLocked $e) {
            return $this->back()->withInput()->withErrors(['code' => $e->getMessage()], 'matter_type_'.$matterType->getKey());
        }

        return $this->back()->with('status', 'Matter type updated. Matters opened from now on use the new details.');
    }

    public function destroy(MatterType $matterType, RetireMatterType $retireType): RedirectResponse
    {
        Gate::authorize(Permission::ManageMatterTypes->value);

        $outcome = $retireType->remove($matterType);

        return $this->back()->with('status', $outcome === 'retired'
            ? 'Matter type retired. It is no longer offered, and its matters still read correctly.'
            : 'Matter type deleted.');
    }

    public function restore(MatterType $matterType, RetireMatterType $retireType): RedirectResponse
    {
        Gate::authorize(Permission::ManageMatterTypes->value);

        $retireType->restore($matterType);

        return $this->back()->with('status', 'Matter type restored.');
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('reference-data', ['tab' => 'matter-types']);
    }
}
