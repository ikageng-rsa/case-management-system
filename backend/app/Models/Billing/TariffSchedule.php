<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Concerns\RecordsActivity;
use App\Enums\Billing\TariffScale;
use App\Models\Document\Media;
use App\Models\User\Role;
use Carbon\CarbonInterface;
use Database\Factories\Billing\TariffScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/*
 * One court tariff, as the firm uploaded it, with what each role charges
 * against it. It is a draft until it is published, and then in force from its
 * date until a later schedule starts, so uploading next year's tariff never
 * changes the rates that applied before it.
 */
#[Fillable(['name', 'effective_from', 'published_at'])]
class TariffSchedule extends Model implements HasMedia
{
    /** @use HasFactory<TariffScheduleFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use RecordsActivity;

    /** The spreadsheet the schedule was read from, kept as it was uploaded. */
    public const SOURCE = 'source';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TariffItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(TariffAdjustment::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::SOURCE)
            ->singleFile()
            ->useDisk(config('media-library.disk_name'));
    }

    public function attachSource(string|UploadedFile $file): Media
    {
        return $this->addMedia($file)->toMediaCollection(static::SOURCE);
    }

    public function source(): ?Media
    {
        return $this->getFirstMedia(static::SOURCE);
    }

    /** The schedule whose rates apply on a date: the latest one to have started by then. */
    public static function inForceOn(CarbonInterface $date): ?self
    {
        return static::query()
            ->whereNotNull('published_at')
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Published and past its date, so its rates may already have been charged. */
    public function hasStarted(): bool
    {
        return $this->isPublished() && $this->effective_from->lte(today());
    }

    /** draft, upcoming, in_force or replaced. */
    public function status(?self $inForce): string
    {
        return match (true) {
            ! $this->isPublished() => 'draft',
            ! $this->hasStarted() => 'upcoming',
            $inForce?->is($this) ?? false => 'in_force',
            default => 'replaced',
        };
    }

    /** What a role charges for an item on a scale: the gazette rate with the role's markup. */
    public function rateFor(TariffItem $item, TariffScale $scale, ?Role $role): ?int
    {
        $rate = $item->rate($scale);

        if ($rate === null) {
            return null;
        }

        $adjustment = $role === null ? null : $this->adjustments->firstWhere('role_id', $role->getKey());

        return $adjustment?->apply($rate) ?? $rate;
    }

    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('effective_from');
    }

    public function auditLabel(): string
    {
        return 'tariff schedule '.$this->name;
    }
}
