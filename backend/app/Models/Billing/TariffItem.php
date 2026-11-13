<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\TariffScale;
use App\Models\Reference\ActivityType;
use Database\Factories\Billing\TariffItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * One line of a tariff: what a kind of work is worth per unit of its measure
 * on each scale the gazette quotes it on. Work counted in pages or kilometres
 * can carry a second rate for the time it took, charged per block of minutes.
 */
#[Fillable(['activity_type_id', 'scale_a_cents', 'scale_b_cents', 'scale_c_cents', 'scale_d_cents', 'time_rate_cents', 'time_block_minutes'])]
class TariffItem extends Model
{
    /** @use HasFactory<TariffItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scale_a_cents' => 'integer',
            'scale_b_cents' => 'integer',
            'scale_c_cents' => 'integer',
            'scale_d_cents' => 'integer',
            'time_rate_cents' => 'integer',
            'time_block_minutes' => 'integer',
        ];
    }

    public static function scaleColumn(TariffScale $scale): string
    {
        return 'scale_'.strtolower($scale->value).'_cents';
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TariffSchedule::class, 'tariff_schedule_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class)->withTrashed();
    }

    /** The gazette's rate on a scale, or null when it is not quoted on that scale. */
    public function rate(TariffScale $scale): ?int
    {
        return $this->{static::scaleColumn($scale)};
    }

    public function chargesTime(): bool
    {
        return $this->time_rate_cents !== null;
    }
}
