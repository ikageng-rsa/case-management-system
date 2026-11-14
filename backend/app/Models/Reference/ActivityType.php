<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Concerns\RecordsActivity;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Narration\Narration;
use Database\Factories\Reference\ActivityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Appends(['unit_label'])]
#[Fillable(['code', 'name', 'measure', 'increment', 'default_billable', 'requires_court', 'time_activity_type_id'])]
#[RouteKey('code')]
class ActivityType extends Model
{
    /** @use HasFactory<ActivityTypeFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

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

    /** The code the system itself files an opening narration under; it cannot be changed or retired. */
    public const SYSTEM_CODES = ['ADMIN'];

    public function isSystem(): bool
    {
        return in_array($this->code, self::SYSTEM_CODES, true);
    }

    /** The kind of work counted in minutes whose rate the time spent on this work is charged at. */
    public function timeActivityType(): BelongsTo
    {
        return $this->belongsTo(self::class, 'time_activity_type_id')->withTrashed();
    }

    /** Whether a narration of this work also records the time it took, charged on top. */
    public function chargesTimeOnTop(): bool
    {
        return $this->time_activity_type_id !== null;
    }

    /*
     * Work that is offered when logging a narration. A kind that only carries
     * the rate other work charges its time at, such as travelling time, is left
     * out: its time is logged with the work it belongs to.
     */
    public function scopeNarratable(Builder $query): void
    {
        $query->whereNotIn('id', self::withTrashed()->whereNotNull('time_activity_type_id')->select('time_activity_type_id'));
    }

    /** Whether other work charges its time at this kind's rate, so it is not logged on its own. */
    public function isTimeRate(): bool
    {
        return self::withTrashed()->where('time_activity_type_id', $this->getKey())->exists();
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
     * How the measure reads on a narration, e.g. "per page" or "6 minutes".
     */
    protected function unitLabel(): Attribute
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

    public function auditLabel(): string
    {
        return 'activity type '.$this->name;
    }
}
