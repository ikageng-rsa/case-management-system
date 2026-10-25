<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\FilterAuditLogRequest;
use App\Models\Audit\Activity;
use App\Models\Matter\Matter;
use App\Queries\Audit\AuditLogExport;
use App\Queries\Audit\AuditLogPage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(FilterAuditLogRequest $request, AuditLogPage $page): View
    {
        Gate::authorize(Permission::ViewAuditLog->value);

        return view('audit-log', [
            'actor' => $request->actor(),
            'action' => $request->action(),
            'canExport' => $request->user()->can(Permission::ExportAuditLog->value),
            'matterOf' => fn (Activity $activity): ?Matter => $activity->belongingMatter(),
        ] + $page->for($request->actor(), $request->action()));
    }

    public function export(FilterAuditLogRequest $request, AuditLogExport $export): StreamedResponse
    {
        Gate::authorize(Permission::ExportAuditLog->value);

        // Taking the log out of the system is itself something the log records.
        activity('audit_log')
            ->event('exported')
            ->withProperties(['ip' => $request->ip(), 'actor' => $request->actor(), 'action' => $request->action()])
            ->log(sprintf('%s exported the audit log', $request->user()->name));

        $rows = $export->for($request->actor(), $request->action());

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, AuditLogExport::HEADINGS);

            $rows->each(fn (array $row) => fputcsv($out, $row));

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
