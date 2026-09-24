<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsActivity;
use App\Enums\Client\PopiaConsentMethod;
use Database\Factories\PopiaConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['granted', 'granted_at', 'withdrawn_at', 'method'])]
class PopiaConsent extends Model
{
    /** @use HasFactory<PopiaConsentFactory> */
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

    public function scopeActive(Builder $query): void
    {
        $query->where('granted', true)->whereNull('withdrawn_at');
    }

    public function auditLabel(): string
    {
        return 'POPIA consent for client '.$this->client?->full_name;
    }
}
