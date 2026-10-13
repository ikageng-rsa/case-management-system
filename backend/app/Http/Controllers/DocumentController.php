<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Document\DocumentKind;
use App\Http\Requests\Document\UploadDocumentRequest;
use App\Models\Matter;
use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Spatie\Activitylog\Models\Activity;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $matterId = $request->query('matter');
        $kind = DocumentKind::tryFrom((string) $request->query('type'));

        $visible = fn (): Builder => Media::query()
            ->where('model_type', (new Matter)->getMorphClass())
            ->where('collection_name', Matter::DOCUMENTS)
            ->whereIn('model_id', $this->visibleMatters($user)->select('matters.id'));

        $documents = $visible()
            ->when($matterId, fn (Builder $query) => $query->where('model_id', $matterId))
            ->when($kind, fn (Builder $query) => $query->where('custom_properties->kind', $kind->value))
            ->with('model')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Who filed a document and how often it was downloaded are not columns
        // of their own; the audit log holds both.
        $activity = Activity::query()
            ->where('subject_type', 'document')
            ->whereIn('subject_id', $documents->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->whereIn('event', ['uploaded', 'downloaded'])
            ->with('causer')
            ->get()
            ->groupBy('subject_id');

        return view('documents', [
            'documents' => $documents,
            'uploadedBy' => $activity->map(fn ($entries) => $entries->firstWhere('event', 'uploaded')?->causer),
            'downloads' => $activity->map(fn ($entries) => $entries->where('event', 'downloaded')->count()),
            'fileCount' => $visible()->count(),
            'totalSize' => Number::fileSize((int) $visible()->sum('size')),
            'matterId' => $matterId,
            'kind' => $kind,
            'kinds' => DocumentKind::cases(),
            'filterMatters' => $this->visibleMatters($user)->orderBy('reference')->get(['matters.id', 'matters.reference']),
            // The matter the upload sheet starts on: the one being filtered by, or the one a failed upload named.
            'uploadMatter' => Matter::query()->open()->assignedTo($user)->find($request->old('matter_id') ?? $matterId),
        ]);
    }

    public function store(UploadDocumentRequest $request): RedirectResponse
    {
        $matter = Matter::findOrFail($request->validated('matter_id'));

        Gate::authorize('work', $matter);

        $matter->addDocument($request->file('file'), DocumentKind::from($request->validated('kind')))
            ->recordAccess('uploaded');

        return redirect()
            ->route('documents', ['matter' => $matter->getKey()])
            ->with('status', 'Document uploaded.');
    }

    /** @return Builder<Matter> */
    protected function visibleMatters(User $user): Builder
    {
        return Matter::query()
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user));
    }
}
