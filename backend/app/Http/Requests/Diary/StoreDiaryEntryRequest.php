<?php

declare(strict_types=1);

namespace App\Http\Requests\Diary;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiaryEntryRequest extends FormRequest
{
    protected $errorBag = 'diary';

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
            'body' => ['required', 'string', 'max:2000'],
            'due_at' => ['required', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
        ];
    }
}
