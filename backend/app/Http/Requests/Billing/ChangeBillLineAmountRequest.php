<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeBillLineAmountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->errorBag = 'bill_line_'.$this->route('line')?->getKey();
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
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
        ];
    }

    public function amountCents(): int
    {
        return (int) round((float) $this->validated('amount') * 100);
    }
}
