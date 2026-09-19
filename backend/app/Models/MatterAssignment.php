<?php

namespace App\Models;

use App\Enums\Matter\Assignment;
use Database\Factories\MatterAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['capacity', 'assigned_at', 'unassigned_at'])]
class MatterAssignment extends Model
{
    /** @use HasFactory<MatterAssignmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (MatterAssignment $assignment) {
            $assignment->assigned_at ??= now();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => Assignment::class,
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
     * Assignments are kept rather than deleted, so the file history shows who
     * carried a matter and when. An unassigned record is history, not current.
     */
    public function isActive(): bool
    {
        return $this->unassigned_at === null;
    }

    public function unassign(): void
    {
        $this->update(['unassigned_at' => now()]);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('unassigned_at');
    }

    public function scopeInCapacity(Builder $query, Assignment $capacity): void
    {
        $query->where('capacity', $capacity);
    }
}
