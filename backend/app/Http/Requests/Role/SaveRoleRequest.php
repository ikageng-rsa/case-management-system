<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use App\Enums\Auth\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    protected $errorBag = 'role_new';

    protected function prepareForValidation(): void
    {
        $role = $this->route('role');

        $this->errorBag = $role ? 'role_'.$role->getKey() : 'role_new';
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Roles are named freely but only choose among the permissions the system
     * defines; a permission cannot be invented here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->getKey()),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_column(Permission::cases(), 'value'))],
        ];
    }
}
