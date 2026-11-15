<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\FeeAgreementKind;
use App\Rules\Document\AllowedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreFeeAgreementRequest extends FormRequest
{
    protected $errorBag = 'fee_agreement';

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
            'kind' => ['required', Rule::enum(FeeAgreementKind::class)],
            'percent' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'signed_on' => ['nullable', 'date', 'before_or_equal:today'],
            // A scanned page or two, so far below the limit for documents filed on a matter.
            'signed_copy' => ['nullable', 'file', 'max:8192', new AllowedDocument],
        ];
    }

    public function kind(): FeeAgreementKind
    {
        return FeeAgreementKind::from($this->validated('kind'));
    }

    /** The agreed share, or null for the firm's default. */
    public function percent(): ?float
    {
        return $this->filled('percent') ? (float) $this->validated('percent') : null;
    }

    public function signedOn(): ?Carbon
    {
        return $this->filled('signed_on') ? Carbon::parse($this->validated('signed_on')) : null;
    }
}
