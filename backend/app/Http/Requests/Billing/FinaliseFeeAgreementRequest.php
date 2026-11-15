<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FinaliseFeeAgreementRequest extends FormRequest
{
    protected $errorBag = 'fee_agreement_finalise';

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
            'amount_received' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ];
    }

    public function amountReceivedCents(): int
    {
        return (int) round((float) $this->validated('amount_received') * 100);
    }
}
