<?php

declare(strict_types=1);

namespace App\Http\Resources\Diary;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Matter\MatterResource;
use App\Http\Resources\User\UserResource;
use App\Models\Diary\DiaryEntry;
use App\Services\Diary\ReminderLead;
use Illuminate\Http\Request;

/**
 * Something to do on a matter by a time, and who it is for.
 *
 * @mixin DiaryEntry
 */
class DiaryEntryResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'diary-entries';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'body' => $this->body,
            'due_at' => $this->due_at->toIso8601String(),
            'status' => $this->isPending() ? 'pending' : 'completed',
            // A pending entry can be ticked off once its time has come.
            'is_due' => $this->isDue(),
            'is_overdue' => $this->isOverdue(),
            'remind_before_minutes' => $this->remind_before_minutes,
            'reminder_label' => ReminderLead::label($this->remind_before_minutes),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'narration_id' => $this->narration_id,
        ];
    }

    /**
     * The matter is given only where the entries span matters and it was loaded.
     *
     * @return array<string, class-string>
     */
    public function toRelationships(Request $request): array
    {
        return [
            'matter' => MatterResource::class,
            'assignee' => UserResource::class,
            'completedBy' => UserResource::class,
        ];
    }
}
