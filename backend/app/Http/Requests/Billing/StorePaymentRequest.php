<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\FirmAccount;
use App\Enums\Billing\PaymentMethod;
use App\Rules\Document\AllowedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    protected $errorBag = 'payment';

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
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'account' => ['required', Rule::enum(FirmAccount::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            // A bank confirmation or a slip, so far below the limit for documents filed on a matter.
            'proof' => ['required', 'file', 'max:5120', new AllowedDocument],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['proof.required' => 'Attach the proof of payment. A payment is recorded with it.'];
    }

    /** @return array{amount_cents: int, received_on: string, method: PaymentMethod, account: FirmAccount, reference: ?string} */
    public function payment(): array
    {
        return [
            'amount_cents' => (int) round((float) $this->validated('amount') * 100),
            'received_on' => $this->validated('received_on'),
            'method' => PaymentMethod::from($this->validated('method')),
            'account' => FirmAccount::from($this->validated('account')),
            'reference' => $this->validated('reference'),
        ];
    }

    public function proof(): UploadedFile
    {
        return $this->file('proof');
    }
}
