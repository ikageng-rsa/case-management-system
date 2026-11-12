<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Concerns\RecordsActivity;
use App\Models\Document\Media;
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
 * One published court tariff, as the firm uploaded it. It is in force from its
 * date until a later schedule starts, so uploading next year's tariff never
 * changes the rates that applied before it.
 */
#[Fillable(['name', 'effective_from'])]
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
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TariffItem::class);
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
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }

    public function hasStarted(): bool
    {
        return $this->effective_from->lte(today());
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
