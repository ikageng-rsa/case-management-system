<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsActivity;
use App\Enums\Matter\PartyRole;
use Database\Factories\MatterPartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * Someone else in the matter: the other side, their representatives, a witness.
 * The client is not one of them; the matter already has its client.
 */
#[Fillable(['name', 'role', 'representative'])]
class MatterParty extends Model
{
    /** @use HasFactory<MatterPartyFactory> */
    use HasFactory;

    use HasUuids;
    use RecordsActivity;

    protected $table = 'matter_parties';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PartyRole::class,
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function auditLabel(): string
    {
        return $this->role->label().' '.$this->name.' on matter '.$this->matter?->reference;
    }

    protected function auditLogName(): string
    {
        return 'matter_party';
    }
}
