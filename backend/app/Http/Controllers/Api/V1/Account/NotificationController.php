<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Account\ListNotificationsRequest;
use App\Http\Resources\Account\NotificationResource;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

class NotificationController extends Controller
{
    private const PER_PAGE = 25;

    /** What the system has told the person, newest first, with how many they have not read. Only ever their own. */
    public function index(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return NotificationResource::collection(
            ($request->onlyUnread() ? $user->unreadNotifications() : $user->notifications())
                ->latest()
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
        )->additional(['meta' => ['unread' => $user->unreadNotifications()->count()]]);
    }
}
