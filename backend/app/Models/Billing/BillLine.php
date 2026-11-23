<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Database\Factories\Billing\BillLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * One line of a bill, as it was drawn: the date, the item, the description and
 * what it charges as a fee or a disbursement, with where it came from.
 */
#[Fillable([
    'kind', 'position', 'occurred_on', 'item', 'description', 'units', 'rate_cents', 'fee_cents', 'disbursement_cents',
    'narration_id', 'disbursement_id', 'fee_agreement_id', 'user_id', 'original_amount_cents', 'claim',
])]
class BillLine extends Model
{
    /** @use HasFactory<BillLineFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BillLineKind::class,
            'position' => 'integer',
            'occurred_on' => 'date',
            'units' => 'decimal:2',
            'rate_cents' => 'integer',
            'fee_cents' => 'integer',
            'disbursement_cents' => 'integer',
            'original_amount_cents' => 'integer',
        ];
    }

    /**
     * What a line claims while its bill is live, so the same work or cost is
     * never on two live bills of the same kind.
     */
    public static function claimFor(BillKind $kind, BillLineKind $line, int|string $sourceId): string
    {
        $source = match ($line) {
            BillLineKind::Work => 'narration',
            BillLineKind::TimeOnTop => 'time',
            BillLineKind::Disbursement => 'disbursement',
            BillLineKind::AgreementFee => 'agreement',
        };

        return "{$kind->value}:{$source}:{$sourceId}";
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function narration(): BelongsTo
    {
        return $this->belongsTo(Narration::class);
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }

    /** The finalised agreement a fee bill's line charges the share of. */
    public function feeAgreement(): BelongsTo
    {
        return $this->belongsTo(FeeAgreement::class);
    }

    /** Whose time a time-on-top line charges for. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by')->withTrashed();
    }

    /** The amount the line charges, whether as a fee or a disbursement. */
    public function amount(): int
    {
        return $this->fee_cents + $this->disbursement_cents;
    }
}
