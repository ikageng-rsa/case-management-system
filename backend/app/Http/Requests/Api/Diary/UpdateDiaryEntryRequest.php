<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Diary;

use App\Http\Requests\Diary\UpdateDiaryEntryRequest as WebUpdateDiaryEntryRequest;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/*
 * Every editable detail of an entry, sent whole. The entry can only go to
 * someone still with the firm, and be linked to a narration on its own matter.
 */
class UpdateDiaryEntryRequest extends WebUpdateDiaryEntryRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'assigned_to' => ['required', 'uuid', Rule::exists('users', 'id')->withoutTrashed()],
            'body' => ['required', 'string', 'max:2000'],
            'narration_id' => [
                'present',
                'nullable',
                'uuid',
                Rule::exists('narrations', 'id')->where('matter_id', $this->route('matter')->getKey()),
            ],
        ];
    }

    public function assignee(): User
    {
        return User::findOrFail($this->validated('assigned_to'));
    }

    public function narration(): ?Narration
    {
        return $this->validated('narration_id') ? Narration::findOrFail($this->validated('narration_id')) : null;
    }
}
