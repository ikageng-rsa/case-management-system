<?php

declare(strict_types=1);

namespace App\Http\Requests\Matter;

use App\Enums\Matter\PartyRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMatterPartyRequest extends FormRequest
{
    protected $errorBag = 'party_new';

    protected function prepareForValidation(): void
    {
        // Every party has a sheet of their own, and only the one submitted should reopen.
        $party = $this->route('party');

        $this->errorBag = $party ? 'party_'.$party->getKey() : 'party_new';
    }

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
            'role' => ['required', Rule::enum(PartyRole::class)],
            'representative' => ['nullable', 'string', 'max:255'],
        ];
    }
}
