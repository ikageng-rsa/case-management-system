<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Notification\NotificationType;
use App\Http\Requests\Account\UpdateNotificationPreferencesRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 * A person's own page. The staff record on it is shown, not editable: it is
 * kept by the firm's directors, who decide what it says. What the person can
 * change here is what they are told about, and their own password.
 */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account', [
            'user' => $user->load('roles'),
            'notificationTypes' => NotificationType::cases(),
        ]);
    }

    public function updateNotifications(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = collect(NotificationType::cases())
            ->mapWithKeys(fn (NotificationType $type) => [
                $type->value => ['mail' => (bool) data_get($request->validated(), 'email.'.$type->value, false)],
            ])
            ->all();

        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], ['notifications' => $notifications])])->save();

        return redirect()->route('account')->with('status', 'Notification choices saved.');
    }
}
