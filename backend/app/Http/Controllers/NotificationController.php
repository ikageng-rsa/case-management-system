<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 * What the system has told this person. Only ever their own: every lookup goes
 * through the signed-in user's notifications, so another person's cannot be
 * named by guessing an id.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $unreadOnly = $request->query('show') === 'unread';

        return view('notifications', [
            'notifications' => ($unreadOnly ? $user->unreadNotifications() : $user->notifications())
                ->paginate(15)
                ->withQueryString(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'show' => $unreadOnly ? 'unread' : 'all',
        ]);
    }

    /** Open one: it is marked read and the person goes where it points. */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $found = $request->user()->notifications()->findOrFail($notification);

        $found->markAsRead();

        $url = $found->data['url'] ?? null;

        return $url !== null && str_starts_with($url, url('/'))
            ? redirect($url)
            : redirect()->route('notifications');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications')->with('status', 'All marked as read.');
    }
}
