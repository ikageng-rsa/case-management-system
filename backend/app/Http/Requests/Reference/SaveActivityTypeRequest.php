<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use App\Enums\Narration\ActivityMeasure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveActivityTypeRequest extends FormRequest
{
    protected $errorBag = 'activity_type_new';

    protected function prepareForValidation(): void
    {
        $type = $this->route('activityType');

        $this->errorBag = $type ? 'activity_type_'.$type->getKey() : 'activity_type_new';

        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The code, measure and increment are optional when editing, because a kind
     * of work that has been used does not send them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('activityType');
        $presence = $type ? 'sometimes' : 'required';

        return [
            'code' => [$presence, 'string', 'regex:/^[A-Z]{2,10}$/', Rule::unique('activity_types', 'code')->ignore($type?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'measure' => [$presence, Rule::enum(ActivityMeasure::class)],
            'increment' => [$presence, 'integer', 'min:1', 'max:1000'],
            'default_billable' => ['sometimes', 'boolean'],
            'requires_court' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.regex' => 'A code is two to ten capital letters.'];
    }
}
