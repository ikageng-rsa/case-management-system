<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(array_merge($this->getFillable(), $this->auditAlso()))
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
        if ($verb === 'read' && ! $this->claimReadWindow()) {
            return;
        }

        activity($this->auditLogName())
            ->performedOn($this)
            ->withProperties($properties)
            ->event($verb)
            ->log($this->describeActivity($verb));
    }

    /**
     * Record something that happened to this record which the model's own logging
     * cannot say: a change to an attribute kept out of the log, such as a contact
     * value. The sentence and properties name what changed, never the value.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordEvent(string $event, string $description, array $properties = []): void
    {
        activity($this->auditLogName())
            ->performedOn($this)
            ->withProperties($properties)
            ->event($event)
            ->log($description);
    }

    /*
     * A read is recorded once per person per record for the length of the
     * window, however often the page is reloaded in it. The cache entry is
     * added atomically, so two requests arriving together cannot both record.
     */
    protected function claimReadWindow(): bool
    {
        $key = sprintf('audit:read:%s:%s:%s', Auth::id() ?? 'guest', $this->getMorphClass(), $this->getKey());

        return Cache::add($key, true, now()->addMinutes(config('audit.read_window_minutes')));
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
     * Attributes to log in addition to the fillable ones: a foreign key that is set
     * through its relation, and so is not mass assignable, is still a change to the record.
     *
     * @return array<int, string>
     */
    protected function auditAlso(): array
    {
        return [];
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
