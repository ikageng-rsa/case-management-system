<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Notification\NotificationType;
use App\Http\Requests\Account\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Account\UpdateSignInCodeRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 * A person's own page. The staff record on it is shown, not editable: it is
 * kept by the firm's directors, who decide what it says. What the person can
 * change here is what they are told about, and their own sign-in.
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
            'channels' => NotificationType::channels(),
        ]);
    }

    public function updateNotifications(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = collect(NotificationType::cases())
            ->mapWithKeys(fn (NotificationType $type) => [
                $type->value => collect(NotificationType::channels())
                    ->mapWithKeys(fn (string $channel) => [
                        $channel => (bool) data_get($request->validated(), "channels.{$type->value}.{$channel}", false),
                    ])
                    ->all(),
            ])
            ->all();

        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], ['notifications' => $notifications])])->save();

        return redirect()->route('account')->with('status', 'Notification choices saved.');
    }

    public function updateSignInCode(UpdateSignInCodeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $enabled = $request->boolean('enabled');

        if ($enabled === $user->usesSignInCode()) {
            return redirect()->route('account');
        }

        $user->forceFill(['sign_in_code_enabled' => $enabled])->save();

        $user->recordEvent(
            $enabled ? 'sign_in_code_enabled' : 'sign_in_code_disabled',
            sprintf('%s turned the sign-in code %s', $user->name, $enabled ? 'on' : 'off'),
        );

        return redirect()->route('account')->with('status', $enabled ? 'The sign-in code is on.' : 'The sign-in code is off.');
    }
}
