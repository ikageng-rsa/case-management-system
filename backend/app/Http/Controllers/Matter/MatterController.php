<?php

declare(strict_types=1);

namespace App\Http\Controllers\Matter;

use App\Enums\Matter\Assignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Matter\AssignUserRequest;
use App\Http\Requests\Matter\ListMattersRequest;
use App\Http\Requests\Matter\ReopenMatterRequest;
use App\Http\Requests\Matter\ShowMatterRequest;
use App\Http\Requests\Matter\StoreMatterRequest;
use App\Http\Requests\Matter\UpdateMatterRequest;
use App\Models\ActivityType;
use App\Models\Client;
use App\Models\Court;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\Narration;
use App\Models\User;
use App\Queries\Matters\MatterAuditTrail;
use App\Queries\Matters\MatterDiary;
use App\Queries\Matters\MatterDocuments;
use App\Queries\Matters\MatterLedger;
use App\Queries\Matters\MatterParties;
use App\Queries\Matters\MatterRecentReads;
use App\Services\Diary\ResolveReminderLead;
use App\Services\Matters\AssignUserToMatter;
use App\Services\Matters\CannotAssignToClosedMatter;
use App\Services\Matters\CloseMatter;
use App\Services\Matters\MatterReference;
use App\Services\Matters\OpenMatter;
use App\Services\Matters\ReopenMatter;
use App\Services\Matters\SaveMatterParty;
use App\Services\Matters\UnassignUserFromMatter;
use App\Services\Matters\UpdateMatterDetails;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MatterController extends Controller
{
    /** The activity an opening narration is filed under. */
    private const OPENING_ACTIVITY = 'ADMIN';

    public function index(ListMattersRequest $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $filter = $request->filter();
        $sort = $request->sort();
        $matterTypeId = $request->matterTypeId();

        $visible = fn (): Builder => Matter::query()
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user));

        $unassigned = fn (Builder $query) => $query->whereDoesntHave(
            'assignments',
            fn (Builder $query) => $query->active()->inCapacity(Assignment::Responsible),
        );

        $matters = $visible()
            ->when($matterTypeId, fn (Builder $query) => $query->where('matter_type_id', $matterTypeId))
            ->tap(fn (Builder $query) => match ($filter) {
                'mine' => $query->open()->assignedTo($user),
                'unassigned' => $unassigned($query->open()),
                'closed' => $query->closed(),
                default => $query->open(),
            })
            ->with([
                'client',
                'matterType',
                'assignments.user',
                'diaryEntries' => fn ($query) => $query->pending(),
            ])
            ->withCount('media')
            ->tap(fn (Builder $query) => match ($sort) {
                'opened' => $query->latest('instructed_at'),
                'reference' => $query->orderBy('reference'),
                // Matters with no pending diary entry sort last.
                default => $query
                    ->withMin(['diaryEntries as next_due_at' => fn ($query) => $query->pending()], 'due_at')
                    ->orderByRaw('next_due_at is null')
                    ->orderBy('next_due_at'),
            })
            ->paginate()
            ->appends($request->except('new'));

        $canOpen = $user->can('create', Matter::class);

        return view('matters', [
            'courts' => $canOpen ? Court::query()->orderBy('name')->get() : collect(),
            'attorneys' => $user->can('assign_matters') ? User::query()->orderBy('name')->get() : collect(),
            'selectedClient' => $request->old('client_id') ? Client::find($request->old('client_id')) : null,
            'matters' => $matters,
            'filter' => $filter,
            'sort' => $sort,
            'matterTypeId' => $matterTypeId,
            'matterTypes' => MatterType::query()->orderBy('name')->get(),
            'counts' => [
                'open' => $visible()->open()->count(),
                'mine' => $visible()->open()->assignedTo($user)->count(),
                'unassigned' => $unassigned($visible()->open())->count(),
                'closed' => $visible()->closed()->count(),
                'closed_this_year' => $visible()->closed()->whereYear('closed_at', now()->year)->count(),
            ],
        ]);
    }

    public function show(
        ShowMatterRequest $request,
        Matter $matter,
        ResolveReminderLead $resolveLead,
        MatterLedger $ledger,
        MatterDocuments $documents,
        MatterParties $parties,
        MatterDiary $diary,
        MatterAuditTrail $auditTrail,
        MatterRecentReads $recentReads,
    ): View {
        Gate::authorize('view', $matter);

        /** @var User $user */
        $user = $request->user();

        $tabs = ['ledger', 'documents', 'parties', 'diary'];

        if ($user->can('viewAudit', $matter)) {
            $tabs[] = 'audit';
        }

        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'ledger';

        $matter->load([
            'client',
            'matterType',
            'court',
            'assignments' => fn ($query) => $query->active(),
            'assignments.user',
        ]);

        $matter->recordAccess('read', ['ip' => $request->ip()]);

        return view('matter-show', [
            'matter' => $matter,
            'tab' => $tab,
            'tabs' => $tabs,
            'nextDate' => $matter->diaryEntries()->pending()->orderBy('due_at')->first()?->due_at,
            'canWork' => $user->can('work', $matter),
            'defaultReminder' => $resolveLead->for($user),
            'activityTypes' => ActivityType::query()->orderBy('name')->get(),
            'courts' => Court::query()->orderBy('name')->orderBy('seat')->get(),
            'assignable' => $user->can('assign', $matter) ? User::query()->orderBy('name')->get() : collect(),
            'capacities' => Assignment::cases(),
            'team' => $matter->assignments->pluck('user')->unique('id')->values(),
            'recentDocuments' => $matter->documents(),
            'recentReads' => $recentReads->for($matter),
        ] + match ($tab) {
            'ledger' => $ledger->for($matter, $user, $request->ledgerFilter()),
            'documents' => $documents->for($matter, $request->documentKind()),
            'parties' => $parties->for($matter, $user),
            'diary' => $diary->for($matter, $user, $request->diaryShow()),
            'audit' => ['activities' => $auditTrail->for($matter)],
        });
    }

    public function store(StoreMatterRequest $request, OpenMatter $openMatter, AssignUserToMatter $assignUser, SaveMatterParty $saveParty): RedirectResponse
    {
        Gate::authorize('create', Matter::class);

        $matter = DB::transaction(function () use ($request, $openMatter, $assignUser, $saveParty): Matter {
            $matter = $openMatter->open(
                client: Client::findOrFail($request->validated('client_id')),
                matterType: MatterType::findOrFail($request->validated('matter_type_id')),
                title: $request->validated('title'),
                court: $request->court(),
                instructedAt: $request->date('instructed_at'),
            );

            $attorney = $request->responsibleAttorney();

            if ($attorney) {
                $assignUser->assign($matter, $attorney, Assignment::Responsible);
            }

            $narration = $request->openingNarration();

            if ($narration) {
                $this->openingNarration($matter, $request->user(), $narration);
            }

            foreach ($request->validated('parties') ?? [] as $party) {
                $saveParty->save($matter, null, $party);
            }

            return $matter;
        });

        return redirect()
            ->route('matter.show', $matter)
            ->with('status', 'Matter opened.');
    }

    /**
     * Matters for a picker in a form: the few that best fit what has been typed.
     * "fileable" narrows them to the open matters the user may put work on.
     */
    public function search(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $fileable = $request->query('scope') === 'fileable';

        return response()->json(
            Matter::query()
                ->when(
                    $fileable,
                    fn (Builder $query) => $query->open()->assignedTo($user),
                    fn (Builder $query) => $query->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user)),
                )
                ->search((string) $request->query('q'))
                ->with(['client', 'matterType'])
                ->limit(5)
                ->get()
                ->map(fn (Matter $matter) => [
                    'id' => $matter->getKey(),
                    'reference' => $matter->reference,
                    'title' => $matter->title,
                    'client' => $matter->client->full_name,
                    'type' => $matter->matterType->name,
                    'year' => $matter->opened_year,
                    'closed' => $matter->isClosed(),
                ]),
        );
    }

    /** What the reference of the matter being opened would be, were it opened now. */
    public function previewReference(Request $request): JsonResponse
    {
        Gate::authorize('create', Matter::class);

        $data = $request->validate([
            'client_id' => ['required', 'uuid', Rule::exists('clients', 'id')->withoutTrashed()],
            'matter_type_id' => ['required', 'integer', Rule::exists('matter_types', 'id')->withoutTrashed()],
            'instructed_at' => ['nullable', 'date'],
        ]);

        $reference = MatterReference::for(
            Client::findOrFail($data['client_id']),
            MatterType::findOrFail($data['matter_type_id']),
            (int) ($request->date('instructed_at') ?? now())->year,
        );

        return response()->json(['reference' => $reference->getReference()]);
    }

    /**
     * The words the matter is opened with go on the ledger as its first entry.
     * It records no work, so it carries no quantity and bills nothing.
     */
    protected function openingNarration(Matter $matter, User $author, string $body): void
    {
        $narration = new Narration(['body' => $body, 'quantity' => 0]);

        $narration->matter()->associate($matter);
        $narration->author()->associate($author);
        $narration->activityType()->associate(ActivityType::query()->where('code', self::OPENING_ACTIVITY)->firstOrFail());

        $narration->save();
    }

    public function update(UpdateMatterRequest $request, Matter $matter, UpdateMatterDetails $updateDetails): RedirectResponse
    {
        Gate::authorize('editDetails', $matter);

        $data = $request->validated();

        $updateDetails->update($matter, Arr::except($data, 'reason'), $data['reason'] ?? null);

        return redirect()->route('matter.show', $matter)->with('status', 'Matter updated.');
    }

    public function close(Matter $matter, CloseMatter $closeMatter): RedirectResponse
    {
        Gate::authorize('close', $matter);

        $closeMatter->close($matter);

        return redirect()->route('matter.show', $matter)->with('status', 'Matter closed.');
    }

    public function reopen(ReopenMatterRequest $request, Matter $matter, ReopenMatter $reopenMatter): RedirectResponse
    {
        Gate::authorize('reopen', $matter);

        $reopenMatter->reopen($matter, $request->validated('reason'));

        return redirect()->route('matter.show', $matter)->with('status', 'Matter reopened.');
    }

    public function assign(AssignUserRequest $request, Matter $matter, AssignUserToMatter $assignUser): RedirectResponse
    {
        Gate::authorize('assign', $matter);

        try {
            $assignUser->assign(
                $matter,
                User::findOrFail($request->validated('user_id')),
                Assignment::from($request->validated('capacity')),
            );
        } catch (CannotAssignToClosedMatter $e) {
            return back()->withErrors(['matter' => $e->getMessage()], 'assignment');
        }

        return redirect()->route('matter.show', [$matter, 'tab' => 'parties']);
    }

    public function unassign(AssignUserRequest $request, Matter $matter, UnassignUserFromMatter $unassignUser): RedirectResponse
    {
        Gate::authorize('assign', $matter);

        $unassignUser->unassign(
            $matter,
            User::findOrFail($request->validated('user_id')),
            Assignment::from($request->validated('capacity')),
        );

        return redirect()->route('matter.show', [$matter, 'tab' => 'parties']);
    }
}
