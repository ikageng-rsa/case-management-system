<?php

declare(strict_types=1);

namespace App\Models\Client;

use App\Casts\Client\EncryptedContact;
use App\Concerns\RecordsActivity;
use Database\Factories\Client\ClientContactPersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * Someone to call at a client. An entity has no voice of its own, so it is
 * reached through people: a first contact and as many alternatives as it needs.
 */
#[Fillable(['name', 'role', 'mobile', 'email', 'is_primary'])]
class ClientContactPerson extends Model
{
    /** @use HasFactory<ClientContactPersonFactory> */
    use HasFactory;

    use HasUuids;
    use RecordsActivity;

    protected $table = 'client_contact_persons';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mobile' => EncryptedContact::class.':mobile',
            'email' => EncryptedContact::class.':email',
            'is_primary' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function auditLabel(): string
    {
        return 'contact person '.$this->name.' of client '.$this->client?->full_name;
    }

    /**
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['mobile', 'email'];
    }

    protected function auditLogName(): string
    {
        return 'client_contact_person';
    }
}
