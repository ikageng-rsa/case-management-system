<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsActivity;
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
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['reference', 'sequence_number', 'opened_year', 'title', 'instructed_at', 'prescribes_at'])]
#[RouteKey('reference')]
class Matter extends Model implements HasMedia
{
    /** @use HasFactory<MatterFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;
    use RecordsActivity;
    use SoftDeletes;

    /** The single media collection holding everything filed on a matter. */
    public const DOCUMENTS = 'documents';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** The court is set through its relation, so it is not fillable, but moving a matter to another court is a change to the file. */
    protected function auditAlso(): array
    {
        return ['court_id'];
    }

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

    /*
     * References contain slashes (IOT/CIV/105/2026), which make awkward URLs,
     * so the route key swaps them for hyphens: IOT-CIV-105-2026.
     */
    public function getRouteKey(): string
    {
        return Str::replace('/', '-', $this->reference);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where('reference', Str::replace('-', '/', $value))->first()
            // A hyphen inside a client's initials or a type code would not survive the swap.
            ?? $this->whereRaw("replace(reference, '/', '-') = ?", [$value])->first();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function matterType(): BelongsTo
    {
        return $this->belongsTo(MatterType::class)->withTrashed();
    }

    /** Null for non-litigious matters, which are not before a court. */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class)->withTrashed();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MatterAssignment::class);
    }

    /** Everyone else in the matter, besides its client: the other side, counsel, witnesses. */
    public function parties(): HasMany
    {
        return $this->hasMany(MatterParty::class);
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

    /*
     * Finds matters word by word, so a few loose fragments narrow it down the way
     * a person would say them: "civ 2026", "armstrong crm", "civ 12". Every word
     * has to match something, but each may match anywhere that fits it: a year
     * word means the year opened, a number means the file number, and any other
     * word may be part of the reference, the title, the type, the client's name or a party's.
     * Words that only say what is being looked for ("matter") are ignored.
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $words = collect(preg_split('/[\s\/\-\.,]+/', mb_strtolower(trim($term))) ?: [])
            ->reject(fn (string $word) => $word === '' || in_array($word, ['matter', 'matters', 'file', 'files'], true))
            ->values();

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(function (Builder $query) use ($word, $like): void {
                $query->where('title', 'like', $like);

                if (ctype_digit($word) && strlen($word) === 4 && $word >= '1900' && $word <= '2100') {
                    $query->orWhere('opened_year', (int) $word);
                } elseif (ctype_digit($word)) {
                    // Files are numbered 1, 2, 3 … though some are written 0001.
                    $number = ltrim($word, '0');

                    if ($number !== '') {
                        $query->orWhereRaw('CAST(sequence_number AS CHAR) like ?', [$number.'%']);
                    }
                } else {
                    $query->orWhere('reference', 'like', $like)
                        ->orWhereHas('matterType', fn (Builder $type) => $type->where('name', 'like', $like))
                        ->orWhereHas('client', fn (Builder $client) => $client
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('entity_name', 'like', $like))
                        ->orWhereHas('parties', fn (Builder $party) => $party
                            ->where('name', 'like', $like)
                            ->orWhere('representative', 'like', $like));
                }
            });
        }

        // Matches that start with what was typed come first, then the most recent.
        $first = $words->first();

        if ($first !== null) {
            $query->orderByRaw('case when lower(reference) like ? then 0 else 1 end', [addcslashes($first, '%_\\').'%']);
        }

        $query->latest('instructed_at');
    }

    /** Matters the given user currently carries, in any capacity. */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->whereHas(
            'assignments',
            fn (Builder $query) => $query->active()->where('user_id', $user->getKey()),
        );
    }

    protected function userAssignedAs(Assignment $capacity): ?User
    {
        return $this->assignments
            ->where('capacity', $capacity)
            ->first(fn (MatterAssignment $assignment) => $assignment->isActive())
            ?->user;
    }

    public function auditLabel(): string
    {
        return 'matter '.$this->reference;
    }
}
