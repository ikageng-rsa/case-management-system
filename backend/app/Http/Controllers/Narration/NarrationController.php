<?php

declare(strict_types=1);

namespace App\Http\Controllers\Narration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Narration\ExportLedgerRequest;
use App\Http\Requests\Narration\StoreNarrationRequest;
use App\Models\ActivityType;
use App\Models\Matter;
use App\Models\Narration;
use App\Models\User;
use App\Queries\Matters\MatterLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NarrationController extends Controller
{
    public function store(StoreNarrationRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('work', $matter);

        $narration = new Narration([
            'body' => $request->validated('body'),
            'quantity' => $request->validated('quantity'),
            'occurred_at' => $request->date('occurred_at'),
        ]);

        $narration->matter()->associate($matter);
        $narration->author()->associate($request->user());
        $narration->activityType()->associate(ActivityType::findOrFail($request->validated('activity_type_id')));

        $narration->save();

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'ledger'])
            ->with('status', 'Narration added.');
    }

    public function export(ExportLedgerRequest $request, Matter $matter, MatterLedger $ledger): StreamedResponse
    {
        Gate::authorize('view', $matter);

        /** @var User $user */
        $user = $request->user();

        $narrations = $ledger->for($matter, $user, $request->filter())['narrations'];

        $matter->recordAccess('exported', ['ip' => $request->ip()]);

        return response()->streamDownload(function () use ($narrations): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Date', 'Activity', 'Author', 'Attendance', 'Quantity', 'Unit', 'Billable units']);

            foreach ($narrations as $narration) {
                fputcsv($out, [
                    $narration->occurred_at->format('Y-m-d H:i'),
                    $narration->activityType->name,
                    $narration->author->name,
                    $narration->body,
                    $narration->quantity,
                    $narration->activityType->measure->value,
                    $narration->billableUnits(),
                ]);
            }

            fclose($out);
        }, str_replace('/', '-', $matter->reference).'-ledger.csv', ['Content-Type' => 'text/csv']);
    }
}
