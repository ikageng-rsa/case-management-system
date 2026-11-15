<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveFirmSettingsRequest extends FormRequest
{
    protected $errorBag = 'firm_settings';

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
            // One line of the address per line of the box.
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** @return array{name: string, addressLines: list<string>, phone: ?string, email: ?string} */
    public function settings(): array
    {
        return [
            'name' => trim($this->validated('name')),
            'addressLines' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->validated('address'))))),
            'phone' => $this->filled('phone') ? trim($this->validated('phone')) : null,
            'email' => $this->filled('email') ? trim($this->validated('email')) : null,
        ];
    }
}
