<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Concerns\RecordsActivity;
use App\Enums\Billing\TariffAdjustmentKind;
use App\Models\User\Role;
use Database\Factories\Billing\TariffAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * What one role charges against a tariff: a percentage of every rate, or a
 * fixed amount on every unit. A role without one charges the gazette rate.
 */
#[Fillable(['role_id', 'kind', 'value'])]
class TariffAdjustment extends Model
{
    /** @use HasFactory<TariffAdjustmentFactory> */
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
            'kind' => TariffAdjustmentKind::class,
            'value' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TariffSchedule::class, 'tariff_schedule_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** A rate in cents, adjusted for this role, rounded to the cent and never below nothing. */
    public function apply(int $cents): int
    {
        $adjusted = match ($this->kind) {
            TariffAdjustmentKind::Percent => (int) round($cents * (10000 + $this->value) / 10000),
            TariffAdjustmentKind::Amount => $cents + $this->value,
        };

        return max(0, $adjusted);
    }

    /** How the adjustment reads, e.g. "+45.45%" or "−R 15.00". */
    public function label(): string
    {
        $sign = $this->value < 0 ? '−' : '+';
        $size = abs($this->value) / 100;

        return match ($this->kind) {
            TariffAdjustmentKind::Percent => $sign.rtrim(rtrim(number_format($size, 2, '.', ''), '0'), '.').'%',
            TariffAdjustmentKind::Amount => $sign.'R '.number_format($size, 2, '.', ' '),
        };
    }

    public function auditLabel(): string
    {
        return 'tariff markup for '.$this->role?->name;
    }
}
