<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Billing\InvoiceTheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceSettingsRequest extends FormRequest
{
    protected $errorBag = 'invoice_settings';

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
            'theme' => ['required', Rule::enum(InvoiceTheme::class)],
            'accent_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255', 'required_with:account_number'],
            'account_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ]+$/', 'required_with:bank_name'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'account_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accent_colour.regex' => 'Choose a colour.',
            'account_number.regex' => 'An account number is digits only.',
        ];
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $text = fn (string $field) => $this->filled($field) ? trim((string) $this->validated($field)) : null;

        return [
            'theme' => InvoiceTheme::from($this->validated('theme')),
            'accentColour' => strtoupper($this->validated('accent_colour')),
            'paymentTerms' => $text('payment_terms'),
            'accountName' => $text('account_name'),
            'bankName' => $text('bank_name'),
            'accountNumber' => $text('account_number'),
            'branchCode' => $text('branch_code'),
            'accountType' => $text('account_type'),
        ];
    }
}
