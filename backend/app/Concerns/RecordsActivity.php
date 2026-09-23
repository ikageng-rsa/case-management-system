<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait RecordsActivity
{
    use LogsActivity;

    /**
     * Accesses already recorded this request, so rendering a record
     * twice does not write the same entry twice.
     *
     * @var array<string, true>
     */
    protected static array $recordedAccesses = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept($this->auditExcept())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->auditLogName())
            ->setDescriptionForEvent(fn (string $event) => $this->describeActivity($event));
    }

    /**
     * Record that someone read, viewed or downloaded this record.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordAccess(string $verb = 'read', array $properties = []): void
    {
        $key = $this->auditLogName().':'.$this->getKey().':'.$verb;

        if (isset(static::$recordedAccesses[$key])) {
            return;
        }

        static::$recordedAccesses[$key] = true;

        activity($this->auditLogName())
            ->performedOn($this)
            ->withProperties($properties)
            ->event($verb)
            ->log($this->describeActivity($verb));
    }

    /**
     * A sentence a person can read without knowing the schema,
     * such as "T. Baloyi downloaded document bundle.pdf".
     */
    public function describeActivity(string $event): string
    {
        return trim(sprintf('%s %s %s', $this->auditCauser(), $event, $this->auditLabel()));
    }

    /**
     * How this record is named in an audit sentence.
     */
    public function auditLabel(): string
    {
        return $this->auditLogName().' '.$this->getKey();
    }

    protected function auditCauser(): string
    {
        return Auth::user()?->name ?? 'System';
    }

    /**
     * Attributes that must never be written into the activity log.
     *
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return [];
    }

    protected function auditLogName(): string
    {
        return Str::snake(class_basename($this));
    }
}
