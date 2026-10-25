<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

class ListClientsRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** A name, an ID or registration number, or a mobile or email on file. */
    public function search(): string
    {
        return (string) $this->validated('q');
    }
}
