<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use Illuminate\Foundation\Http\FormRequest;

class ListUsersRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** What has been typed into the picker, if anything. */
    public function search(): ?string
    {
        return $this->filled('q') ? trim((string) $this->validated('q')) : null;
    }
}
