<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Diary;

use App\Http\Requests\Api\Diary\Concerns\ChoosesReminderInMinutes;
use App\Http\Requests\Diary\UpdateDiaryReminderRequest as WebUpdateDiaryReminderRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/** The web's reminder change, with the lead as minutes. It has to be sent, as null to turn the reminder off. */
class UpdateDiaryReminderRequest extends WebUpdateDiaryReminderRequest
{
    use ChoosesReminderInMinutes;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = $this->reminderRules();

        return ['remind_before_minutes' => ['present', ...$rules['remind_before_minutes']]];
    }
}
