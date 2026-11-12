<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\TariffScale;
use App\Models\Reference\ActivityType;
use App\Models\User\Role;
use Database\Factories\Billing\TariffItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * One rate in a tariff schedule: what a kind of work is worth per unit of its
 * measure, on a scale and for a role, or for any scale or role when those are
 * left empty. Work counted in pages or kilometres can carry a second rate for
 * the time it took, charged per block of minutes.
 */
#[Fillable(['activity_type_id', 'scale', 'role_id', 'unit_rate_cents', 'time_rate_cents', 'time_block_minutes'])]
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
            'scale' => TariffScale::class,
            'unit_rate_cents' => 'integer',
            'time_rate_cents' => 'integer',
            'time_block_minutes' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TariffSchedule::class, 'tariff_schedule_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class)->withTrashed();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function chargesTime(): bool
    {
        return $this->time_rate_cents !== null;
    }
}
