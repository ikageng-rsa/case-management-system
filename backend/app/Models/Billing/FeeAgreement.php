<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Concerns\RecordsActivity;
use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Document\Media;
use App\Models\Matter\Matter;
use App\Models\User\User;
use Database\Factories\Billing\FeeAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/*
 * How a client agreed to be charged on a matter, with the signed copy. The
 * percentage is kept as it was agreed, whatever the firm's default becomes.
 */
#[Fillable(['kind', 'percent_hundredths', 'status', 'signed_on', 'amount_received_cents', 'closed_at'])]
class FeeAgreement extends Model implements HasMedia
{
    /** @use HasFactory<FeeAgreementFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use RecordsActivity;

    public const SIGNED_COPY = 'signed_copy';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeeAgreementKind::class,
            'status' => FeeAgreementStatus::class,
            'percent_hundredths' => 'integer',
            'signed_on' => 'date',
            'amount_received_cents' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::SIGNED_COPY)
            ->singleFile()
            ->useDisk(config('media-library.disk_name'));
    }

    public function attachSignedCopy(string|UploadedFile $file): Media
    {
        return $this->addMedia($file)->toMediaCollection(static::SIGNED_COPY);
    }

    public function signedCopy(): ?Media
    {
        return $this->getFirstMedia(static::SIGNED_COPY);
    }

    public function isActive(): bool
    {
        return $this->status === FeeAgreementStatus::Active;
    }

    public function percent(): ?float
    {
        return $this->percent_hundredths === null ? null : $this->percent_hundredths / 100;
    }

    /** The firm's share of what was received, once the agreement is finalised. */
    public function fee(): ?int
    {
        if ($this->amount_received_cents === null || $this->percent_hundredths === null) {
            return null;
        }

        return (int) round($this->amount_received_cents * $this->percent_hundredths / 10000);
    }

    /** e.g. "Contingency, 25%" or "Mandate". */
    public function label(): string
    {
        if ($this->percent() === null) {
            return $this->kind->label();
        }

        return $this->kind->label().', '.rtrim(rtrim(number_format($this->percent(), 2, '.', ''), '0'), '.').'%';
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', FeeAgreementStatus::Active);
    }

    public function auditLabel(): string
    {
        return 'fee agreement on matter '.$this->matter?->reference;
    }
}
