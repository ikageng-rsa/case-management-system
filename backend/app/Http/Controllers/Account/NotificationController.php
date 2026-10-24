<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/*
 * What the system has told this person, for the list that drops down from the
 * bell. Only ever their own: every lookup goes through the signed-in user's
 * notifications, so another person's cannot be named by guessing an id.
 */
class NotificationController extends Controller
{
    /** How many the list holds; it is for what is new, not an archive. */
    private const LIST_SIZE = 10;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit(self::LIST_SIZE)
                ->get()
                ->map(fn (DatabaseNotification $notification) => $this->present($notification))
                ->values(),
        ]);
    }

    /** Mark one read, and say where it points so the list can take the person there. */
    public function read(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $found = $user->notifications()->findOrFail($notification);
        $found->markAsRead();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'item' => $this->present($found->refresh()),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $notification): array
    {
        $url = $notification->data['url'] ?? null;

        return [
            'id' => $notification->getKey(),
            'message' => $notification->data['message'] ?? 'Something needs your attention.',
            // Only a place in this app is ever offered as somewhere to go.
            'url' => is_string($url) && str_starts_with($url, url('/')) ? $url : null,
            'read' => $notification->read_at !== null,
            'when' => $notification->created_at->diffForHumans(),
        ];
    }
}
