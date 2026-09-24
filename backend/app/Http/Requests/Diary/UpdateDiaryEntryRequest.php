<?php

declare(strict_types=1);

namespace App\Http\Requests\Diary;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiaryEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The update replaces every editable detail. Date
     * is not forced, can be future or past
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'due_at' => ['required', 'date'],
            // The narration must be sent, even as null
            'narration_id' => ['present', 'nullable', 'uuid', Rule::exists('narrations', 'id')],
        ];
    }
}
