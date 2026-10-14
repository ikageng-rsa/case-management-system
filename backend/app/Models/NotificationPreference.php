<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsActivity;
use App\Enums\Notification\NotificationType;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * How one person has chosen to be told about one kind of notification. There is
 * a row only once they have chosen; without one, the kind's own default applies.
 */
#[Fillable(['type', 'database', 'mail', 'lead_minutes'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
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
            'database' => 'boolean',
            'mail' => 'boolean',
            'lead_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLabel(): string
    {
        $type = NotificationType::tryFrom($this->type);

        return 'notification choice "'.($type?->label() ?? $this->type).'" of '.$this->user?->name;
    }

    protected function auditLogName(): string
    {
        return 'notification_preference';
    }
}
