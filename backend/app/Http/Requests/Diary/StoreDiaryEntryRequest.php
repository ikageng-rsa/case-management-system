<?php

declare(strict_types=1);

namespace App\Http\Requests\Diary;

use App\Http\Requests\Diary\Concerns\ChoosesReminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiaryEntryRequest extends FormRequest
{
    use ChoosesReminder;

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
        ] + $this->reminderRules();
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkReminderRange($validator)];
    }
}
