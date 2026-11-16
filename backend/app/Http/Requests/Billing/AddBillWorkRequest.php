<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddBillWorkRequest extends FormRequest
{
    protected $errorBag = 'bill_add';

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
            'narrations' => ['nullable', 'array', 'required_without:disbursements'],
            'narrations.*' => ['string', 'uuid'],
            'disbursements' => ['nullable', 'array', 'required_without:narrations'],
            'disbursements.*' => ['integer'],
            'time_rates' => ['nullable', 'array'],
            'time_rates.*' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'narrations.required_without' => 'Choose the work or disbursements to add.',
            'disbursements.required_without' => 'Choose the work or disbursements to add.',
        ];
    }

    /** @return list<string> */
    public function narrationIds(): array
    {
        return array_values($this->validated('narrations') ?? []);
    }

    /** @return list<int> */
    public function disbursementIds(): array
    {
        return array_map('intval', array_values($this->validated('disbursements') ?? []));
    }

    /** @return array<string, int> cents an hour, by person, for the rates that were given */
    public function timeRates(): array
    {
        return collect($this->validated('time_rates') ?? [])
            ->filter(fn ($rate) => $rate !== null && $rate !== '')
            ->map(fn ($rate) => (int) round((float) $rate * 100))
            ->all();
    }
}
