<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use App\Enums\Document\DocumentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    protected $errorBag = 'document';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:51200'],
            'kind' => ['required', Rule::enum(DocumentKind::class)],
        ];
    }
}
