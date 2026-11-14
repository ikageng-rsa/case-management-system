<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use App\Enums\Narration\ActivityMeasure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'charges_time' => ['sometimes', 'boolean'],
            // The time is charged at the rate of another kind of work counted in minutes.
            'time_activity_type_id' => [
                'nullable', 'required_if_accepted:charges_time', 'integer',
                Rule::exists('activity_types', 'id')->where('measure', ActivityMeasure::Minutes->value)->whereNull('deleted_at'),
                Rule::notIn(array_filter([$type?->getKey()])),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'A code is two to ten capital letters.',
            'time_activity_type_id.required_if_accepted' => 'Choose the kind of work the time is charged at.',
            'time_activity_type_id.exists' => 'Time is charged at a kind of work counted in minutes.',
        ];
    }

    /** Work that is itself counted in minutes is charged by its time already, so it takes none on top. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $measure = $this->input('measure') ?? $this->route('activityType')?->measure->value;

            if ($this->boolean('charges_time') && $measure === ActivityMeasure::Minutes->value) {
                $validator->errors()->add('charges_time', 'This kind of work is counted in minutes, so its time is already charged.');
            }
        }];
    }
}
