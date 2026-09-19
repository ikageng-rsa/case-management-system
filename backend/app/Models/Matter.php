<?php

namespace App\Models;

use App\Enums\Matter\Assignment;
use Database\Factories\MatterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['reference', 'sequence_number', 'opened_year', 'title', 'instructed_at', 'prescribes_at', 'closed_at'])]
#[RouteKey('reference')]
class Matter extends Model
{
    /** @use HasFactory<MatterFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'opened_year' => 'integer',
            'instructed_at' => 'datetime',
            'prescribes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function matterType(): BelongsTo
    {
        return $this->belongsTo(MatterType::class);
    }

    /** Null for non-litigious matters, which are not before a court. */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MatterAssignment::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'matter_assignments')
            ->withPivot(['capacity', 'assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    public function hasPrescribed(): bool
    {
        if ($this->prescribes_at === null) {
            return false;
        }

        return $this->prescribes_at->isPast();
    }

    /** The attorney carrying the file. A matter has one at a time. */
    public function responsibleAttorney(): ?User
    {
        return $this->userAssignedAs(Assignment::Responsible);
    }

    public function supervisingAttorney(): ?User
    {
        return $this->userAssignedAs(Assignment::Supervising);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignments
            ->where('user_id', $user->getKey())
            ->contains(fn (MatterAssignment $assignment) => $assignment->isActive());
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function scopeClosed(Builder $query): void
    {
        $query->whereNotNull('closed_at');
    }

    /** Open matters whose prescription date falls inside the warning window. */
    public function scopePrescribingWithin(Builder $query, int $days): void
    {
        $query->whereNull('closed_at')
            ->whereNotNull('prescribes_at')
            ->whereBetween('prescribes_at', [now(), now()->addDays($days)]);
    }

    public function scopePrescribed(Builder $query): void
    {
        $query->whereNotNull('prescribes_at')->where('prescribes_at', '<', now());
    }

    protected function userAssignedAs(Assignment $capacity): ?User
    {
        return $this->assignments
            ->where('capacity', $capacity)
            ->first(fn (MatterAssignment $assignment) => $assignment->isActive())
            ?->user;
    }
}
