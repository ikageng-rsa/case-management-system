<?php

declare(strict_types=1);

namespace App\Http\Resources\Account;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Something the system told the person. It names the matter or diary entry it
 * is about, so the app can open it; the web's link is not the app's to follow.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'notifications';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'kind' => $this->data['type'] ?? null,
            'message' => $this->data['message'] ?? 'Something needs your attention.',
            'matter_id' => $this->data['matter_id'] ?? null,
            'diary_entry_id' => isset($this->data['diary_entry_id']) ? (string) $this->data['diary_entry_id'] : null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
