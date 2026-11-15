<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\FirmAccount;
use App\Rules\Document\AllowedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisbursementRequest extends FormRequest
{
    protected $errorBag = 'disbursement';

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
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999'],
            'paid_from' => ['required', Rule::enum(FirmAccount::class)],
            // A scanned page or two, so far below the limit for documents filed on a matter.
            'receipt' => ['nullable', 'file', 'max:8192', new AllowedDocument],
        ];
    }

    /** @return array{incurred_on: string, description: string, amount_cents: int, paid_from: FirmAccount} */
    public function disbursement(): array
    {
        return [
            'incurred_on' => $this->validated('incurred_on'),
            'description' => $this->validated('description'),
            'amount_cents' => (int) round((float) $this->validated('amount') * 100),
            'paid_from' => FirmAccount::from($this->validated('paid_from')),
        ];
    }
}
