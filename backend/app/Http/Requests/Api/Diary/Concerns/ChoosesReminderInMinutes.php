<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Diary\Concerns;

use App\Services\Diary\ReminderLead;
use Illuminate\Validation\Validator;

/*
 * The app sends a reminder as one number, remind_before_minutes, in place of the
 * web form's choice, number and unit. Null is no reminder; leaving it out is
 * "as I usually like", where a form allows that.
 */
trait ChoosesReminderInMinutes
{
    /** @return array<string, mixed> */
    protected function reminderRules(): array
    {
        return [
            'remind_before_minutes' => ['nullable', 'integer', 'min:'.ReminderLead::MINIMUM, 'max:'.ReminderLead::MAXIMUM],
        ];
    }

    public function choseReminder(): bool
    {
        return $this->exists('remind_before_minutes');
    }

    public function reminderMinutes(): ?int
    {
        $minutes = $this->validated('remind_before_minutes');

        return $minutes === null ? null : (int) $minutes;
    }

    /** The range is held by the rules, since the lead arrives as one number. */
    protected function checkReminderRange(Validator $validator): void {}
}
