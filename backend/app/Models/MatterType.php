<?php

namespace App\Models;

use Database\Factories\MatterTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'default_prescription_months', 'is_litigation'])]
#[RouteKey('code')]
class MatterType extends Model
{
    /** @use HasFactory<MatterTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_prescription_months' => 'integer',
            'is_litigation' => 'boolean',
        ];
    }

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }

    /** Not every matter type prescribes. */
    public function prescribes(): bool
    {
        return $this->default_prescription_months !== null;
    }

    public function scopeLitigation(Builder $query): void
    {
        $query->where('is_litigation', true);
    }
}
