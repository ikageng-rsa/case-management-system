<?php

declare(strict_types=1);

namespace App\Http\Requests\Diary;

use App\Http\Requests\Diary\Concerns\ChoosesReminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDiaryReminderRequest extends FormRequest
{
    use ChoosesReminder;

    protected $errorBag = 'diary_reminder';

    protected function prepareForValidation(): void
    {
        // Every entry has a sheet of its own, and only the one submitted should reopen.
        $this->errorBag = 'diary_reminder_'.$this->route('diaryEntry')->getKey();
    }

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
        return ['remind' => ['required']] + $this->reminderRules();
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkReminderRange($validator)];
    }
}
