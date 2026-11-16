<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VoidBillRequest extends FormRequest
{
    protected $errorBag = 'bill_void';

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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Say why the bill is being voided. It stays on record with the reason.'];
    }
}
