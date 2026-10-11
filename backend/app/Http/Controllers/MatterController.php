<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Matter\Assignment;
use App\Http\Requests\Matter\AssignUserRequest;
use App\Http\Requests\Matter\StoreMatterRequest;
use App\Http\Requests\Matter\UpdateMatterRequest;
use App\Models\Client;
use App\Models\Court;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\User;
use App\Services\Matters\AssignUserToMatter;
use App\Services\Matters\CannotAssignToClosedMatter;
use App\Services\Matters\CloseMatter;
use App\Services\Matters\OpenMatter;
use App\Services\Matters\UnassignUserFromMatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatterController extends Controller
{
    private const FILTERS = ['all', 'mine', 'unassigned', 'closed'];

    private const SORTS = ['next_date', 'opened', 'reference'];

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'next_date';
        $matterTypeId = $request->integer('type') ?: null;

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
            ->withQueryString();

        return view('matters', [
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

    public function show(Matter $matter): View
    {
        Gate::authorize('view', $matter);

        $matter->load([
            'client',
            'matterType',
            'court',
            'assignments.user',
            'narrations',
            'diaryEntries',
        ]);

        return view('matter-show', ['matter' => $matter]);
    }

    public function store(StoreMatterRequest $request, OpenMatter $openMatter): RedirectResponse
    {
        Gate::authorize('create', Matter::class);

        $matter = $openMatter->open(
            client: Client::findOrFail($request->validated('client_id')),
            matterType: MatterType::findOrFail($request->validated('matter_type_id')),
            title: $request->validated('title'),
            court: $request->filled('court_id') ? Court::findOrFail($request->validated('court_id')) : null,
            instructedAt: $request->date('instructed_at'),
        );

        return redirect()->route('matter.show', $matter);
    }

    public function update(UpdateMatterRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('update', $matter);

        $data = $request->validated();

        $matter->fill(collect($data)->only(['title', 'instructed_at'])->all());

        // Non litigious matters are not before a court.
        if (array_key_exists('court_id', $data) && $matter->matterType->is_litigation) {
            $matter->court()->associate($data['court_id'] === null ? null : Court::findOrFail($data['court_id']));
        }

        $matter->save();

        return redirect()->route('matter.show', $matter);
    }

    public function close(Matter $matter, CloseMatter $closeMatter): RedirectResponse
    {
        Gate::authorize('close', $matter);

        $closeMatter->close($matter);

        return redirect()->route('matter.show', $matter);
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
            return back()->withErrors(['matter' => $e->getMessage()]);
        }

        return redirect()->route('matter.show', $matter);
    }

    public function unassign(AssignUserRequest $request, Matter $matter, UnassignUserFromMatter $unassignUser): RedirectResponse
    {
        Gate::authorize('assign', $matter);

        $unassignUser->unassign(
            $matter,
            User::findOrFail($request->validated('user_id')),
            Assignment::from($request->validated('capacity')),
        );

        return redirect()->route('matter.show', $matter);
    }
}
