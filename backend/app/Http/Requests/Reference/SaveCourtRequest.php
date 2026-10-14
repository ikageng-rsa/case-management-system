<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use App\Enums\Court\CourtTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCourtRequest extends FormRequest
{
    protected $errorBag = 'court_new';

    protected function prepareForValidation(): void
    {
        $court = $this->route('court');

        $this->errorBag = $court ? 'court_'.$court->getKey() : 'court_new';
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
            'tier' => ['required', Rule::enum(CourtTier::class)],
            'name' => ['required', 'string', 'max:255'],
            'seat' => ['required', 'string', 'max:255'],
        ];
    }
}
