<?php

namespace App\Models;

use Database\Factories\NarrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body', 'quantity', 'occurred_at'])]
class Narration extends Model
{
    /** @use HasFactory<NarrationFactory> */
    use HasFactory;

    use HasUuids;

    protected static function booted(): void
    {
        static::creating(function (Narration $narration) {
            $narration->occurred_at ??= now();
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
            'quantity' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Set only for appearances, where the court attended affects the tariff. */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function billableUnits(): int
    {
        return $this->activityType->billableUnits((float) $this->quantity);
    }

    public function scopeForMatter(Builder $query, Matter $matter): void
    {
        $query->where('matter_id', $matter->getKey());
    }

    public function scopeBy(Builder $query, User $author): void
    {
        $query->where('author_id', $author->getKey());
    }

    /** Narrations that fall inside a billing period, by when the work happened. */
    public function scopeOccurredBetween(Builder $query, mixed $from, mixed $to): void
    {
        $query->whereBetween('occurred_at', [$from, $to]);
    }

    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('occurred_at');
    }
}
