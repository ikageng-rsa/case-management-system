<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsActivity;
use App\Enums\Client\PopiaConsentMethod;
use Database\Factories\PopiaConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['granted', 'granted_at', 'withdrawn_at', 'method'])]
class PopiaConsent extends Model implements HasMedia
{
    /** @use HasFactory<PopiaConsentFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;
    use RecordsActivity;

    /** The signed mandate evidencing this consent decision, when one was captured. */
    public const MANDATE = 'mandate';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'method' => PopiaConsentMethod::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Consent counts only while it has been granted and not since withdrawn. */
    public function isActive(): bool
    {
        if (! $this->granted) {
            return false;
        }

        return $this->withdrawn_at === null;
    }

    public function withdraw(): void
    {
        $this->update(['withdrawn_at' => now()]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::MANDATE)
            ->singleFile()
            ->useDisk(config('media-library.disk_name'));
    }

    /** The signed mandate on file, or null when consent was captured another way. */
    public function mandate(): ?Media
    {
        return $this->getFirstMedia(static::MANDATE);
    }

    public function addMandate(string|UploadedFile $file): Media
    {
        return $this->addMedia($file)->toMediaCollection(static::MANDATE);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('granted', true)->whereNull('withdrawn_at');
    }

    public function auditLabel(): string
    {
        return 'POPIA consent for client '.$this->client?->full_name;
    }
}
