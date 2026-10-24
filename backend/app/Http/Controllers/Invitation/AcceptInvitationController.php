<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Models\Invitation\UserInvitation;
use App\Services\Invitations\AcceptInvitation;
use App\Services\Invitations\InvitationNoLongerValid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/*
 * Where someone arrives from the link in their email. Nothing here says whether
 * an address belongs to a user: a link that does not work is simply no longer
 * valid, whatever the reason.
 */
class AcceptInvitationController extends Controller
{
    public function show(string $token): View|Response
    {
        $invitation = UserInvitation::query()->where('token_hash', UserInvitation::hash($token))->first();

        if ($invitation === null || ! $invitation->isPending()) {
            return response()->view('invitations.invalid', [], 410);
        }

        return view('invitations.accept', ['invitation' => $invitation, 'token' => $token]);
    }

    public function store(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse|Response
    {
        try {
            $acceptInvitation->accept($token, $request->validated('password'));
        } catch (InvitationNoLongerValid) {
            return response()->view('invitations.invalid', [], 410);
        }

        return redirect()->route('login')->with('status', 'Your account is ready. Sign in with your email and the password you just chose.');
    }
}
