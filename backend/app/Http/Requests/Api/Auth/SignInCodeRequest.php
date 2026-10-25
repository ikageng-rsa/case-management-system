<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignInCodeRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20'],
        ];
    }
}
