<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Enums\Auth\Permission;
use App\Models\Invitation\UserInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    protected $errorBag = 'invite';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            // How long the link lives is the inviter's to choose.
            'lifetime_days' => ['required', 'integer', Rule::in(UserInvitation::LIFETIMES)],
            // Giving someone roles is assigning roles, which has its own permission.
            'roles' => $this->user()->can(Permission::AssignRoles->value) ? ['nullable', 'array'] : ['prohibited'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ];
    }
}
