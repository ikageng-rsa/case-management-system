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
            'tagline' => ['nullable', 'string', 'max:100'],
            // One line of the address per line of the box.
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            // A picture of the letterhead strip, small enough to print into every bill.
            'letterhead' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_letterhead' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array{name: string, tagline: ?string, addressLines: list<string>, phone: ?string, email: ?string, website: ?string, registrationNumber: ?string} */
    public function settings(): array
    {
        return [
            'name' => trim($this->validated('name')),
            'tagline' => $this->filled('tagline') ? trim($this->validated('tagline')) : null,
            'addressLines' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->validated('address'))))),
            'phone' => $this->filled('phone') ? trim($this->validated('phone')) : null,
            'email' => $this->filled('email') ? trim($this->validated('email')) : null,
            'website' => $this->filled('website') ? trim($this->validated('website')) : null,
            'registrationNumber' => $this->filled('registration_number') ? trim($this->validated('registration_number')) : null,
        ];
    }
}
