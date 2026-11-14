<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Concerns\RecordsActivity;
use App\Enums\Billing\FirmAccount;
use App\Models\Document\Media;
use App\Models\Matter\Matter;
use App\Models\User\User;
use Database\Factories\Billing\DisbursementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/*
 * Money the firm paid out on a matter for the client, with the account it came
 * from and the receipt. It is billed at cost, under disbursements.
 */
#[Fillable(['incurred_on', 'description', 'amount_cents', 'paid_from'])]
class Disbursement extends Model implements HasMedia
{
    /** @use HasFactory<DisbursementFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use RecordsActivity;

    public const RECEIPT = 'receipt';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'incurred_on' => 'date',
            'amount_cents' => 'integer',
            'paid_from' => FirmAccount::class,
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
        $this->addMediaCollection(static::RECEIPT)
            ->singleFile()
            ->useDisk(config('media-library.disk_name'));
    }

    public function attachReceipt(string|UploadedFile $file): Media
    {
        return $this->addMedia($file)->toMediaCollection(static::RECEIPT);
    }

    public function receipt(): ?Media
    {
        return $this->getFirstMedia(static::RECEIPT);
    }

    public function auditLabel(): string
    {
        return 'disbursement on matter '.$this->matter?->reference;
    }
}
