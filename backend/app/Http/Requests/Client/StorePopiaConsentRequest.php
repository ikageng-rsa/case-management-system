<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Client\PopiaConsentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePopiaConsentRequest extends FormRequest
{
    protected $errorBag = 'popia';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * How consent was given decides what evidence it needs: a signed mandate has
     * to be on file, while a verbal or online consent may be recorded without one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PopiaConsentMethod::class)],
            'consented_on' => ['nullable', 'date', 'before_or_equal:today'],
            'mandate' => [
                Rule::requiredIf($this->input('method') === PopiaConsentMethod::Signed->value),
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480',
            ],
        ];
    }
}
