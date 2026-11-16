<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DraftFeeBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /*
     * A matter can have more than one finalised agreement, each with its own
     * sheet, so the errors go back to the sheet of the agreement being billed.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = static::bagFor($this->route('feeAgreement')->getKey());
    }

    public static function bagFor(int|string $feeAgreementId): string
    {
        return "fee_bill_{$feeAgreementId}";
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'addressee' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
