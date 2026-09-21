<?php

namespace App\Models;

use App\Concerns\RecordsActivity;
use App\Enums\Court\CourtTier;
use Database\Factories\CourtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tier', 'name', 'seat'])]
class Court extends Model
{
    /** @use HasFactory<CourtFactory> */
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
            'tier' => CourtTier::class,
        ];
    }

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }

    /** How the court is cited on a pleading, e.g. "High Court, Johannesburg". */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->name}, {$this->seat}");
    }

    public function scopeOfTier(Builder $query, CourtTier $tier): void
    {
        $query->where('tier', $tier);
    }

    public function auditLabel(): string
    {
        return 'court '.$this->name;
    }
}
