<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Concerns\RecordsActivity;
use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillStatus;
use App\Enums\Billing\TariffScale;
use App\Models\Matter\Matter;
use App\Models\User\User;
use Database\Factories\Billing\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/*
 * A statement of account, a bill of cost or a fee bill on a matter. Its lines
 * and totals are kept as they were drawn, so it reads the same whatever happens
 * to the tariff, the narrations or the settings afterwards.
 */
#[Fillable([
    'kind', 'status', 'number', 'addressee', 'addressee_address', 'scale', 'period_from', 'period_to',
    'fees_cents', 'drawing_fee_percent_hundredths', 'drawing_fee_cents', 'disbursements_cents', 'total_cents',
    'issued_at', 'voided_at', 'void_reason',
])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    use HasUuids;
    use RecordsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BillKind::class,
            'status' => BillStatus::class,
            'scale' => TariffScale::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'fees_cents' => 'integer',
            'drawing_fee_percent_hundredths' => 'integer',
            'drawing_fee_cents' => 'integer',
            'disbursements_cents' => 'integer',
            'total_cents' => 'integer',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class)->orderBy('position')->orderBy('id');
    }

    public function draftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drafted_by')->withTrashed();
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    public function isDraft(): bool
    {
        return $this->status === BillStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status === BillStatus::Issued;
    }

    public function isVoid(): bool
    {
        return $this->status === BillStatus::Void;
    }

    /** The running part of the bill's number, e.g. 0001 of INV/2026/0001, as it is quoted on the bill. */
    public function shortNumber(): ?string
    {
        return $this->number === null ? null : Str::afterLast($this->number, '/');
    }

    /** The line printed above a bill's lines, saying what it is and on which scale. */
    public function heading(string $firm): string
    {
        return match ($this->kind) {
            BillKind::Statement => "Statement of account for fees due to {$firm} on attorney and own client scale",
            BillKind::BillOfCost => "Bill of cost of {$firm} on the party and party scale".($this->scale ? " (scale {$this->scale->value})" : ''),
            BillKind::AgreementFee => "Account for fees due to {$firm} under the fee agreement",
        };
    }

    /** Bills that are not void: drafts and issued bills hold on to the work on them. */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', '!=', BillStatus::Void);
    }

    public function auditLabel(): string
    {
        return strtolower($this->kind->label()).' '.($this->number ?? '(draft)').' on matter '.$this->matter?->reference;
    }
}
