<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Matter;

use App\Enums\Matter\Assignment;
use App\Http\Requests\Matter\AssignUserRequest as WebAssignUserRequest;
use App\Models\User\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/*
 * The web's assignment rules, with one more: only someone still with the firm
 * can be put on a matter, the same people the app's picker offers.
 */
class AssignUserRequest extends WebAssignUserRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')->withoutTrashed()],
        ];
    }

    public function assignee(): User
    {
        return User::findOrFail($this->validated('user_id'));
    }

    public function capacity(): Assignment
    {
        return Assignment::from($this->validated('capacity'));
    }
}
