<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Concerns\RecordsActivity;
use App\Enums\Billing\FirmAccount;
use App\Enums\Billing\PaymentMethod;
use App\Models\Document\Media;
use App\Models\User\User;
use Database\Factories\Billing\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/*
 * Money received on an issued bill: how much, when, how it came, which of the
 * firm's accounts it went into, and the proof of payment. One recorded in error
 * is reversed and kept, so the books show what happened.
 */
#[Fillable(['amount_cents', 'received_on', 'method', 'account', 'reference', 'reversed_at', 'reverse_reason'])]
class Payment extends Model implements HasMedia
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use RecordsActivity;

    public const PROOF = 'proof';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'received_on' => 'date',
            'method' => PaymentMethod::class,
            'account' => FirmAccount::class,
            'reversed_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by')->withTrashed();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::PROOF)
            ->singleFile()
            ->useDisk(config('media-library.disk_name'));
    }

    public function attachProof(string|UploadedFile $file): Media
    {
        return $this->addMedia($file)->toMediaCollection(static::PROOF);
    }

    public function proof(): ?Media
    {
        return $this->getFirstMedia(static::PROOF);
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /** e.g. RCT-00042. */
    public function receiptNumber(): string
    {
        return 'RCT-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    /** What was still owed on the bill once this payment was in, counting the payments up to it. */
    public function balanceAfterCents(): int
    {
        $paid = (int) $this->bill->payments()->reorder()->live()
            ->where(fn (Builder $query) => $query
                ->where('received_on', '<', $this->received_on->toDateString())
                ->orWhere(fn (Builder $same) => $same->whereDate('received_on', $this->received_on)->where('id', '<=', $this->getKey())))
            ->sum('amount_cents');

        return max(0, $this->bill->total_cents - $paid);
    }

    /** Payments that still count towards what a bill has been paid. */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('reversed_at');
    }

    public function auditLabel(): string
    {
        return 'payment on '.$this->bill?->auditLabel();
    }
}
