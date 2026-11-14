<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Account;

use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'unread' => ['nullable', 'boolean'],
        ];
    }

    /** Whether to list only what has not been read yet. */
    public function onlyUnread(): bool
    {
        return $this->boolean('unread');
    }
}
