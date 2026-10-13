<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Auth\Permission;
use App\Http\Requests\Role\SaveRoleRequest;
use App\Http\Requests\User\UpdateUserRolesRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Roles\CannotDeleteRoleInUse;
use App\Services\Roles\CannotRemoveLastAdministrator;
use App\Services\Roles\DeleteRole;
use App\Services\Roles\SaveRole;
use App\Services\Users\AssignUserRoles;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permission::ManageUsers->value);

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
            'roles' => Role::query()->withCount('users')->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::cases(),
        ]);
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

    public function storeRole(SaveRoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        Gate::authorize(Permission::AssignRoles->value);

        $saveRole->save(null, $request->validated('name'), $request->validated('permissions') ?? []);

        return redirect()->route('users')->with('status', 'Role created.');
    }

    public function updateRole(SaveRoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        Gate::authorize(Permission::AssignRoles->value);

        try {
            $saveRole->save($role, $request->validated('name'), $request->validated('permissions') ?? []);
        } catch (CannotRemoveLastAdministrator $e) {
            return back()->withInput()->withErrors(['permissions' => $e->getMessage()], 'role_'.$role->getKey());
        }

        return redirect()->route('users')->with('status', 'Role updated.');
    }

    public function destroyRole(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        Gate::authorize(Permission::AssignRoles->value);

        try {
            $deleteRole->delete($role);
        } catch (CannotDeleteRoleInUse|CannotRemoveLastAdministrator $e) {
            return back()->withErrors(['role' => $e->getMessage()], 'roles');
        }

        return redirect()->route('users')->with('status', 'Role deleted.');
    }
}
