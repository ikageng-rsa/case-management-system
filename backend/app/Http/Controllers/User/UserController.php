<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\SaveRoleRequest;
use App\Http\Requests\User\DeactivateUserRequest;
use App\Http\Requests\User\UpdateUserDetailsRequest;
use App\Http\Requests\User\UpdateUserRolesRequest;
use App\Models\Invitation\UserInvitation;
use App\Models\User\Role;
use App\Models\User\User;
use App\Queries\Users\UserWorkload;
use App\Services\Roles\CannotDeleteRoleInUse;
use App\Services\Roles\CannotRemoveLastAdministrator;
use App\Services\Roles\DeleteRole;
use App\Services\Roles\SaveRole;
use App\Services\Users\AssignUserRoles;
use App\Services\Users\CannotDeactivateUser;
use App\Services\Users\DeactivateUser;
use App\Services\Users\ReactivateUser;
use App\Services\Users\UpdateUserDetails;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request, UserWorkload $workload): View
    {
        Gate::authorize(Permission::ViewUsers->value);

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // The session store already knows when each person was last seen.
        $lastActive = DB::table('sessions')
            ->whereIn('user_id', $users->pluck('id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, max(last_activity) as last_activity')
            ->pluck('last_activity', 'user_id')
            ->map(fn ($timestamp) => Carbon::createFromTimestamp((int) $timestamp));

        return view('users', [
            'users' => $users,
            'total' => User::query()->count(),
            'invitations' => UserInvitation::query()->outstanding()->with('inviter')->latest()->get(),
            'lifetimes' => UserInvitation::LIFETIMES,
            'defaultLifetime' => UserInvitation::DEFAULT_LIFETIME,
            'lastActive' => $lastActive,
            'deactivated' => User::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'workloads' => $request->user()->can(Permission::DeactivateUsers->value)
                ? $users->getCollection()->mapWithKeys(fn (User $person) => [$person->getKey() => $workload->for($person)])
                : collect(),
            'staff' => User::query()->orderBy('name')->get(['id', 'name', 'job_title']),
            'roles' => Role::query()->withCount('users')->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::cases(),
        ]);
    }

    public function updateDetails(UpdateUserDetailsRequest $request, User $user, UpdateUserDetails $updateDetails): RedirectResponse
    {
        Gate::authorize(Permission::EditUsers->value);

        $updateDetails->update($user, $request->validated());

        return redirect()->route('users')->with('status', 'Details updated.');
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user, AssignUserRoles $assignRoles): RedirectResponse
    {
        Gate::authorize(Permission::AssignRoles->value);

        try {
            $assignRoles->assign($user, $request->validated('roles') ?? []);
        } catch (CannotRemoveLastAdministrator $e) {
            return back()->withInput()->withErrors(['roles' => $e->getMessage()], 'user_roles_'.$user->getKey());
        }

        return redirect()->route('users')->with('status', 'Roles updated.');
    }

    public function deactivate(DeactivateUserRequest $request, User $user, DeactivateUser $deactivate): RedirectResponse
    {
        Gate::authorize(Permission::DeactivateUsers->value);

        try {
            $deactivate->deactivate($user, $request->user(), $request->validated('reason'), $request->validated('successors') ?? []);
        } catch (CannotDeactivateUser|CannotRemoveLastAdministrator $e) {
            return back()->withInput()->withErrors(['deactivate' => $e->getMessage()], 'deactivate_user_'.$user->getKey());
        }

        return redirect()->route('users')->with('status', $user->name.' deactivated.');
    }

    public function reactivate(string $user, ReactivateUser $reactivate): RedirectResponse
    {
        Gate::authorize(Permission::DeactivateUsers->value);

        $person = User::onlyTrashed()->findOrFail($user);
        $reactivate->reactivate($person, request()->user());

        return redirect()->route('users')->with('status', $person->name.' reactivated.');
    }

    public function storeRole(SaveRoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        Gate::authorize(Permission::ManageRoles->value);

        $saveRole->save(null, $request->validated('name'), $request->validated('permissions') ?? []);

        return redirect()->route('users')->with('status', 'Role created.');
    }

    public function updateRole(SaveRoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        Gate::authorize(Permission::ManageRoles->value);

        try {
            $saveRole->save($role, $request->validated('name'), $request->validated('permissions') ?? []);
        } catch (CannotRemoveLastAdministrator $e) {
            return back()->withInput()->withErrors(['permissions' => $e->getMessage()], 'role_'.$role->getKey());
        }

        return redirect()->route('users')->with('status', 'Role updated.');
    }

    public function destroyRole(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        Gate::authorize(Permission::ManageRoles->value);

        try {
            $deleteRole->delete($role);
        } catch (CannotDeleteRoleInUse|CannotRemoveLastAdministrator $e) {
            return back()->withErrors(['role' => $e->getMessage()], 'roles');
        }

        return redirect()->route('users')->with('status', 'Role deleted.');
    }
}
