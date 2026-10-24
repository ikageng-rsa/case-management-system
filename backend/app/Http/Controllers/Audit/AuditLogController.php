<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Matter\MatterParty;
use App\Models\Media;
use App\Models\Narration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(Permission::ViewAuditLog->value);

        $activities = $this->filtered($request)
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

        return view('audit-log', [
            'activities' => $activities,
            'actor' => $request->query('actor'),
            'action' => $request->query('action'),
            'actors' => User::query()
                ->whereIn('id', Activity::query()->whereNotNull('causer_id')->select('causer_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'actions' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'canExport' => $request->user()->can(Permission::ExportAuditLog->value),
            'matterOf' => fn (Activity $activity): ?Matter => $this->matterOf($activity->subject),
            'details' => $activities->getCollection()->mapWithKeys(fn (Activity $activity) => [$activity->getKey() => $this->detail($activity)]),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize(Permission::ExportAuditLog->value);

        // Taking the log out of the system is itself something the log records.
        activity('audit_log')
            ->event('exported')
            ->withProperties(['ip' => $request->ip(), 'actor' => $request->query('actor'), 'action' => $request->query('action')])
            ->log(sprintf('%s exported the audit log', $request->user()->name));

        $query = $this->filtered($request)->with(['causer', 'subject'])->latest('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['When', 'Actor', 'Action', 'Description', 'Matter', 'IP']);

            $query->lazyById(500)->each(function (Activity $activity) use ($out): void {
                fputcsv($out, [
                    $activity->created_at->format('Y-m-d H:i:s'),
                    $activity->causer?->name ?? 'System',
                    $activity->event,
                    $activity->description,
                    $this->matterOf($activity->subject)?->reference,
                    $activity->properties['ip'] ?? null,
                ]);
            });

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return Builder<Activity> */
    protected function filtered(Request $request): Builder
    {
        return Activity::query()
            ->when($request->query('actor'), fn (Builder $query, string $actor) => $query->where('causer_id', $actor))
            ->when($request->query('action'), fn (Builder $query, string $action) => $query->where('event', $action));
    }

    /**
     * Everything the log holds about an entry, in the shape the detail sheet reads.
     *
     * @return array<string, mixed>
     */
    protected function detail(Activity $activity): array
    {
        $subject = $activity->subject;
        $matter = $this->matterOf($subject);
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

    protected function matterOf(?Model $subject): ?Matter
    {
        return match (true) {
            $subject instanceof Matter => $subject,
            $subject instanceof Narration, $subject instanceof DiaryEntry, $subject instanceof MatterAssignment, $subject instanceof MatterParty => $subject->matter,
            $subject instanceof Media => $subject->model instanceof Matter ? $subject->model : null,
            default => null,
        };
    }
}
