<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsActivity;
use Database\Factories\UserInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/*
 * An invitation to join the firm. The person is not a user until they accept:
 * until then there is only this record, so nobody half-made can be assigned
 * work, shown in a list of staff, or signed in as.
 */
#[Fillable(['name', 'email', 'roles', 'lifetime_days'])]
class UserInvitation extends Model
{
    /** @use HasFactory<UserInvitationFactory> */
    use HasFactory;

    use HasUuids;
    use RecordsActivity;

    /** The lifetimes an invitation may be given, in days. */
    public const LIFETIMES = [1, 3, 7, 14, 30];

    public const DEFAULT_LIFETIME = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'lifetime_days' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** The only form a token is ever stored in. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return ! $this->isAccepted() && ! $this->isRevoked() && $this->expires_at->isPast();
    }

    /** Still able to be accepted. */
    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isRevoked() && ! $this->expires_at->isPast();
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /** Not accepted and not withdrawn, whether or not the link has run out. */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at');
    }

    public function auditLabel(): string
    {
        return 'invitation for '.$this->name;
    }

    /**
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['token_hash'];
    }

    protected function auditLogName(): string
    {
        return 'user_invitation';
    }
}
