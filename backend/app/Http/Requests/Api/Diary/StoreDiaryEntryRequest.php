<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Diary;

use App\Http\Requests\Api\Diary\Concerns\ChoosesReminderInMinutes;
use App\Http\Requests\Diary\StoreDiaryEntryRequest as WebStoreDiaryEntryRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/*
 * The web's diary rules, with the reminder as minutes and the entry only ever
 * for someone still with the firm.
 */
class StoreDiaryEntryRequest extends WebStoreDiaryEntryRequest
{
    use ChoosesReminderInMinutes;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'assigned_to' => ['nullable', 'uuid', Rule::exists('users', 'id')->withoutTrashed()],
        ];
    }
}
