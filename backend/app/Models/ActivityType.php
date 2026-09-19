<?php

namespace App\Models;

use App\Enums\Narration\ActivityMeasure;
use Database\Factories\ActivityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Appends(['name'])]
#[Fillable(['code', 'measure', 'increment', 'default_billable', 'requires_court'])]
#[RouteKey('code')]
class ActivityType extends Model
{
    /** @use HasFactory<ActivityTypeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measure' => ActivityMeasure::class,
            'increment' => 'integer',
            'default_billable' => 'boolean',
            'requires_court' => 'boolean',
        ];
    }

    public function narrations(): HasMany
    {
        return $this->hasMany(Narration::class);
    }

    /*
     * Work is billed in whole increments of the measure — a six-minute unit for
     * time, a single page, a kilometre. A quantity is rounded up to the next
     * increment, so four minutes of attendance still bills one unit.
     */
    public function billableUnits(float $quantity): int
    {
        if ($quantity <= 0) {
            return 0;
        }

        return (int) ceil($quantity / $this->increment);
    }

    /*
     * Compiled label of the record.
     */
    protected function name(): Attribute
    {
        return Attribute::get(function (): string {
            $measure = $this->increment === 1
                ? Str::singular($this->measure->value)
                : Str::plural($this->measure->value);

            if ($this->increment === 1) {
                return "per {$measure}";
            }

            return "{$this->increment} {$measure}";
        });
    }

    public function scopeBillable(Builder $query): void
    {
        $query->where('default_billable', true);
    }

    public function scopeRequiringCourt(Builder $query): void
    {
        $query->where('requires_court', true);
    }
}
