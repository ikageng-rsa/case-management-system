<?php

namespace App\Models;

use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use Database\Factories\MatterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['reference', 'sequence_number', 'opened_year', 'title', 'instructed_at', 'prescribes_at', 'closed_at'])]
#[RouteKey('reference')]
class Matter extends Model implements HasMedia
{
    /** @use HasFactory<MatterFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    /** The single media collection holding everything filed on a matter. */
    public const DOCUMENTS = 'documents';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'opened_year' => 'integer',
            'instructed_at' => 'datetime',
            'prescribes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function matterType(): BelongsTo
    {
        return $this->belongsTo(MatterType::class);
    }

    /** Null for non-litigious matters, which are not before a court. */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MatterAssignment::class);
    }

    public function narrations(): HasMany
    {
        return $this->hasMany(Narration::class);
    }

    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /*
     * Everything filed on the matter lives in one collection, with the kind
     * of document kept as a custom property. Separate collections per kind
     * would make "every document on this file" the awkward query, and that is
     * the one the file view asks for constantly.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::DOCUMENTS)
            ->useDisk(config('media-library.disk_name'));
    }

    /** @return MediaCollection<int, Media> */
    public function documents(): MediaCollection
    {
        return $this->getMedia(static::DOCUMENTS);
    }

    /** @return MediaCollection<int, Media> */
    public function documentsOfKind(DocumentKind $kind): MediaCollection
    {
        return $this->getMedia(
            static::DOCUMENTS,
            fn (Media $media) => $media->getCustomProperty('kind') === $kind->value,
        );
    }

    public function addDocument(string|UploadedFile $file, DocumentKind $kind): Media
    {
        return $this->addMedia($file)
            ->withCustomProperties(['kind' => $kind->value])
            ->toMediaCollection(static::DOCUMENTS);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'matter_assignments')
            ->withPivot(['capacity', 'assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    public function hasPrescribed(): bool
    {
        if ($this->prescribes_at === null) {
            return false;
        }

        return $this->prescribes_at->isPast();
    }

    /** The attorney carrying the file. A matter has one at a time. */
    public function responsibleAttorney(): ?User
    {
        return $this->userAssignedAs(Assignment::Responsible);
    }

    public function supervisingAttorney(): ?User
    {
        return $this->userAssignedAs(Assignment::Supervising);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignments
            ->where('user_id', $user->getKey())
            ->contains(fn (MatterAssignment $assignment) => $assignment->isActive());
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function scopeClosed(Builder $query): void
    {
        $query->whereNotNull('closed_at');
    }

    /** Open matters whose prescription date falls inside the warning window. */
    public function scopePrescribingWithin(Builder $query, int $days): void
    {
        $query->whereNull('closed_at')
            ->whereNotNull('prescribes_at')
            ->whereBetween('prescribes_at', [now(), now()->addDays($days)]);
    }

    public function scopePrescribed(Builder $query): void
    {
        $query->whereNotNull('prescribes_at')->where('prescribes_at', '<', now());
    }

    protected function userAssignedAs(Assignment $capacity): ?User
    {
        return $this->assignments
            ->where('capacity', $capacity)
            ->first(fn (MatterAssignment $assignment) => $assignment->isActive())
            ?->user;
    }
}
