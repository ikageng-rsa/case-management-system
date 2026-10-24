<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reference;

use App\Enums\Auth\Permission;
use App\Enums\Court\CourtTier;
use App\Enums\Narration\ActivityMeasure;
use App\Http\Controllers\Controller;
use App\Models\Reference\ActivityType;
use App\Models\Reference\Court;
use App\Models\Reference\MatterType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/*
 * The lists the rest of the app's forms are filled from. Each is looked after
 * under its own permission, so the page shows a person only the tables they may
 * change, and loads only the one they are looking at.
 */
class ReferenceDataController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $tabs = array_filter([
            'courts' => $user->can(Permission::ManageCourts->value) ? 'Courts' : null,
            'matter-types' => $user->can(Permission::ManageMatterTypes->value) ? 'Matter types' : null,
            'activity-types' => $user->can(Permission::ManageActivityTypes->value) ? 'Kinds of work' : null,
        ]);

        abort_if($tabs === [], 403);

        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : array_key_first($tabs);

        return view('reference-data', [
            'tabs' => $tabs,
            'tab' => $tab,
            'courts' => $tab === 'courts'
                ? Court::withTrashed()->withCount(['matters' => fn ($q) => $q->withTrashed()])->orderBy('name')->get()
                : collect(),
            'matterTypes' => $tab === 'matter-types'
                ? MatterType::withTrashed()->withCount(['matters' => fn ($q) => $q->withTrashed()])->orderBy('name')->get()
                : collect(),
            'activityTypes' => $tab === 'activity-types'
                ? ActivityType::withTrashed()->withCount('narrations')->orderBy('name')->get()
                : collect(),
            'tiers' => CourtTier::cases(),
            'measures' => ActivityMeasure::cases(),
        ]);
    }
}
