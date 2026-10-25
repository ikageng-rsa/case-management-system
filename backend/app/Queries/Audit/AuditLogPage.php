<?php

declare(strict_types=1);

namespace App\Queries\Audit;

use App\Models\Audit\Activity;
use App\Models\Diary\DiaryEntry;
use App\Models\Document\Media;
use App\Models\Matter\MatterAssignment;
use App\Models\Matter\MatterParty;
use App\Models\Narration\Narration;
use App\Models\User\User;

/*
 * One page of the firm's audit log, newest first, with what the filters can
 * offer and everything the detail sheet shows about each entry.
 */
class AuditLogPage
{
    /** @return array<string, mixed> */
    public function for(?string $actor, ?string $action): array
    {
        $activities = Activity::query()
            ->filteredBy($actor, $action)
            ->with(['causer', 'subject'])
            ->latest('id')
            ->paginate(8)
            ->withQueryString();

        // A matter is shown for anything that belongs to one, reached through
        // whatever the entry is about.
        $activities->loadMorph('subject', [
            Narration::class => ['matter'],
            DiaryEntry::class => ['matter'],
            MatterAssignment::class => ['matter'],
            MatterParty::class => ['matter'],
            Media::class => ['model'],
        ]);

        return [
            'activities' => $activities,
            'actors' => User::query()
                ->whereIn('id', Activity::query()->whereNotNull('causer_id')->select('causer_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'actions' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'details' => $activities->getCollection()->mapWithKeys(fn (Activity $activity) => [$activity->getKey() => $this->detail($activity)]),
        ];
    }

    /**
     * Everything the log holds about an entry, in the shape the detail sheet reads.
     *
     * @return array<string, mixed>
     */
    protected function detail(Activity $activity): array
    {
        $subject = $activity->subject;
        $matter = $activity->belongingMatter();
        $changes = $activity->attribute_changes ?? collect();

        $properties = collect($activity->properties ?? [])->except('ip');

        return [
            'when' => $activity->created_at->format('d M Y H:i:s'),
            'actor' => $activity->causer?->name ?? 'System',
            'event' => str_replace('_', ' ', (string) $activity->event),
            'log' => $activity->log_name,
            'description' => $activity->description,
            'subject' => $subject?->auditLabel() ?? $activity->log_name.'#'.$activity->subject_id,
            'subjectDeleted' => $activity->subject_id !== null && $subject === null,
            'matter' => $matter?->reference,
            'matterUrl' => $matter ? route('matter.show', $matter) : null,
            'ip' => $activity->properties['ip'] ?? null,
            'changes' => collect($changes['attributes'] ?? [])
                ->map(fn (mixed $new, string $field) => [
                    'field' => str_replace('_', ' ', $field),
                    'old' => $this->display($changes['old'][$field] ?? null),
                    'new' => $this->display($new),
                ])
                ->values()
                ->all(),
            'properties' => $properties
                ->map(fn (mixed $value, string $key) => [
                    'key' => str_replace('_', ' ', $key),
                    // The log keeps the name of a field as the system knows it; here it reads as words.
                    'value' => $key === 'field' ? str_replace(['_', '-'], ' ', $this->display($value)) : $this->display($value),
                ])
                ->values()
                ->all(),
        ];
    }

    protected function display(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) && array_is_list($value) && collect($value)->every(fn ($item) => is_scalar($item)) => $value === [] ? '—' : implode(', ', $value),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
