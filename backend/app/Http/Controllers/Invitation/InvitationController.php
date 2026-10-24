<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitation;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\InviteUserRequest;
use App\Models\UserInvitation;
use App\Services\Invitations\EmailAlreadyInvited;
use App\Services\Invitations\InvitationNoLongerValid;
use App\Services\Invitations\InviteUser;
use App\Services\Invitations\ResendInvitation;
use App\Services\Invitations\RevokeInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class InvitationController extends Controller
{
    public function store(InviteUserRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        Gate::authorize(Permission::InviteUsers->value);

        try {
            $inviteUser->invite(
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('roles') ?? [],
                (int) $request->validated('lifetime_days'),
                $request->user(),
            );
        } catch (EmailAlreadyInvited $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()], 'invite');
        } catch (Throwable $e) {
            // The invitation is saved; only the email failed to go, and it can be resent.
            report($e);

            return redirect()->route('users')->withErrors(['invitation' => 'The invitation was saved but the email could not be sent. Try resending it.'], 'invitations');
        }

        return redirect()->route('users')->with('status', 'Invitation sent.');
    }

    public function resend(UserInvitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        Gate::authorize(Permission::InviteUsers->value);

        try {
            $resendInvitation->resend($invitation);
        } catch (InvitationNoLongerValid $e) {
            return back()->withErrors(['invitation' => 'That invitation has already been used or withdrawn.'], 'invitations');
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['invitation' => 'The email could not be sent. Try again shortly.'], 'invitations');
        }

        return redirect()->route('users')->with('status', 'Invitation sent again.');
    }

    public function destroy(UserInvitation $invitation, RevokeInvitation $revokeInvitation): RedirectResponse
    {
        Gate::authorize(Permission::InviteUsers->value);

        try {
            $revokeInvitation->revoke($invitation);
        } catch (InvitationNoLongerValid $e) {
            return back()->withErrors(['invitation' => 'That invitation has already been accepted.'], 'invitations');
        }

        return redirect()->route('users')->with('status', 'Invitation withdrawn.');
    }
}
