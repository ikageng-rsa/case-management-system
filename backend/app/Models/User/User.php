<?php

declare(strict_types=1);

namespace App\Models\User;

use App\Casts\Client\EncryptedContact;
use App\Concerns\RecordsActivity;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration\Narration;
use Database\Factories\User\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'job_title', 'mobile', 'office_phone', 'employee_number'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use Notifiable;
    use RecordsActivity;
    use SoftDeletes;

    /**
     * Deactivating and reactivating are recorded by the services that do them, with
     * the reason, so the model's own deleted and restored entries would only repeat them.
     *
     * @var array<int, string>
     */
    protected static array $recordEvents = ['created', 'updated'];

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
            'sign_in_code_enabled' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** What this person has chosen to be told about, and where. Only the choices made: the rest take their defaults. */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
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

    /** Whether signing in asks for a code, which is on until the person turns it off. */
    public function usesSignInCode(): bool
    {
        // A user just created has not been read back, so the column's own default is not on the model yet.
        return (bool) ($this->sign_in_code_enabled ?? true);
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
        return ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'mobile', 'office_phone'];
    }
}
