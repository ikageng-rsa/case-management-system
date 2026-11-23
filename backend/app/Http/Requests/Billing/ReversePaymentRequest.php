<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReversePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /* A bill can have several payments, each with its own sheet, so the errors go back to the one being reversed. */
    protected function prepareForValidation(): void
    {
        $this->errorBag = static::bagFor($this->route('payment')->getKey());
    }

    public static function bagFor(int|string $paymentId): string
    {
        return "payment_reverse_{$paymentId}";
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Say why the payment is being reversed. It stays on record with the reason.'];
    }
}
