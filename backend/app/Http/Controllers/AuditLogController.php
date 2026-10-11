<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Auth\Permission;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\MatterAssignment;
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

    protected function matterOf(?Model $subject): ?Matter
    {
        return match (true) {
            $subject instanceof Matter => $subject,
            $subject instanceof Narration, $subject instanceof DiaryEntry, $subject instanceof MatterAssignment => $subject->matter,
            $subject instanceof Media => $subject->model instanceof Matter ? $subject->model : null,
            default => null,
        };
    }
}
