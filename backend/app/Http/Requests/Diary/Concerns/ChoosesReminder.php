<?php

declare(strict_types=1);

namespace App\Http\Requests\Diary\Concerns;

use App\Services\Diary\ReminderLead;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
 * The three fields of "remind me … before": a choice from the usual steps, or
 * "custom" with a number and a unit. Shared by every form that asks for one.
 */
trait ChoosesReminder
{
    /** @return array<string, mixed> */
    protected function reminderRules(): array
    {
        $choices = ['none', 'custom', ...array_map('strval', array_keys(ReminderLead::PRESETS))];

        return [
            'remind' => ['nullable', Rule::in($choices)],
            'remind_value' => ['nullable', 'required_if:remind,custom', 'integer', 'min:1'],
            'remind_unit' => ['nullable', 'required_if:remind,custom', Rule::in(ReminderLead::units())],
        ];
    }

    /** Whether the form said anything about a reminder at all. */
    public function choseReminder(): bool
    {
        return $this->filled('remind');
    }

    /** The lead in minutes: null is no reminder. Only meaningful when {@see choseReminder()}. */
    public function reminderMinutes(): ?int
    {
        return ReminderLead::minutes((string) $this->input('remind', 'none'), $this->input('remind_value'), $this->input('remind_unit'));
    }

    /** A custom lead has to be one the reminders can actually keep. */
    protected function checkReminderRange(Validator $validator): void
    {
        if ($this->input('remind') !== 'custom' || $validator->errors()->hasAny(['remind_value', 'remind_unit'])) {
            return;
        }

        $minutes = $this->reminderMinutes();

        if ($minutes < ReminderLead::MINIMUM || $minutes > ReminderLead::MAXIMUM) {
            $validator->errors()->add('remind_value', sprintf(
                'A reminder can be %d minutes to %d days before.',
                ReminderLead::MINIMUM,
                intdiv(ReminderLead::MAXIMUM, 1440),
            ));
        }
    }
}
