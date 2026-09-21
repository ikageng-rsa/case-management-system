<?php

namespace App\Models;

use App\Concerns\RecordsActivity;
use Database\Factories\DiaryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body', 'due_at'])]
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
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->completed_at === null;
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
