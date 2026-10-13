<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Casts\Client\EncryptedContact;
use App\Concerns\RecordsActivity;
use App\Enums\Notification\NotificationType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'job_title', 'mobile', 'office_phone', 'employee_number', 'preferences'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use Notifiable;
    use RecordsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mobile' => EncryptedContact::class.':mobile',
            'office_phone' => EncryptedContact::class.':mobile',
            'preferences' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function matterAssignments(): HasMany
    {
        return $this->hasMany(MatterAssignment::class);
    }

    public function narrations(): HasMany
    {
        return $this->hasMany(Narration::class, 'author_id');
    }

    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class, 'assigned_to');
    }

    public function matters(): BelongsToMany
    {
        return $this->belongsToMany(Matter::class, 'matter_assignments')
            ->withPivot(['capacity', 'assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }

    /**
     * Whether this person wants the email for a kind of notification, as they
     * chose it or, failing that, as the system defaults it.
     */
    public function wantsEmailFor(NotificationType $type): bool
    {
        return (bool) ($this->preferences['notifications'][$type->value]['mail'] ?? $type->emailByDefault());
    }

    public function auditLabel(): string
    {
        return 'user '.$this->name;
    }

    /**
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'mobile', 'office_phone', 'preferences'];
    }
}
