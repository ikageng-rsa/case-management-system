<?php

declare(strict_types=1);

namespace App\Models\Diary;

use App\Concerns\RecordsActivity;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User;
use App\Observers\DiaryEntryObserver;
use Carbon\CarbonInterface;
use Database\Factories\Diary\DiaryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['body', 'due_at', 'remind_before_minutes'])]
#[ObservedBy([DiaryEntryObserver::class])]
class DiaryEntry extends Model
{
    /** @use HasFactory<DiaryEntryFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'remind_before_minutes' => 'integer',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** The narration recording the work that completed this entry, when there is one. */
    public function narration(): BelongsTo
    {
        return $this->belongsTo(Narration::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->completed_at === null;
    }

    /** An entry is a reminder of a time, so it cannot be done before that time has come. */
    public function isDue(): bool
    {
        return $this->due_at->isPast();
    }

    public function isComplete(): bool
    {
        return ! $this->isPending();
    }

    /** A completed entry is never overdue, however late it was finished. */
    public function isOverdue(): bool
    {
        if ($this->isComplete()) {
            return false;
        }

        return $this->due_at->isPast();
    }

    /*
     * Completion is never mass-assignable — who signed off on a diary entry is
     * recorded here, from the acting user, not from request input.
     */
    public function markComplete(User $user): void
    {
        $this->forceFill([
            'completed_at' => now(),
            'completed_by' => $user->getKey(),
        ])->save();
    }

    /** Link or, given null, unlink the narration; the caller saves. */
    public function linkNarration(?Narration $narration): void
    {
        if ($narration === null) {
            $this->narration()->dissociate();

            return;
        }

        if ($narration->matter_id !== $this->matter_id) {
            throw new InvalidArgumentException('A diary entry can only be linked to a narration on the same matter.');
        }

        $this->narration()->associate($narration);
    }

    /** Still to do, not yet due, with a reminder asked for and not yet sent. */
    public function scopeAwaitingReminder(Builder $query): void
    {
        $query->whereNull('completed_at')
            ->where('due_at', '>', now())
            ->whereNotNull('remind_before_minutes')
            ->whereNull('reminder_sent_at');
    }

    /** The moment the reminder is due to go out, or null where none was asked for. */
    public function remindAt(): ?CarbonInterface
    {
        return $this->remind_before_minutes === null
            ? null
            : $this->due_at->copy()->subMinutes($this->remind_before_minutes);
    }

    /** Past due, still to do, and nobody has yet been told so. */
    public function scopeAwaitingOverdueNotice(Builder $query): void
    {
        $query->overdue()->whereNull('overdue_notified_at');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('completed_at')->where('due_at', '<', now());
    }

    public function scopeDueWithin(Builder $query, int $days): void
    {
        $query->whereNull('completed_at')
            ->whereBetween('due_at', [now(), now()->addDays($days)]);
    }

    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->where('assigned_to', $user->getKey());
    }

    public function auditLabel(): string
    {
        return 'diary entry on matter '.$this->matter?->reference;
    }
}
