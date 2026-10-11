<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Document\DocumentKind;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class MatterDocumentController extends Controller
{
    public function store(StoreDocumentRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('work', $matter);

        $matter->addDocument($request->file('file'), DocumentKind::from($request->validated('kind')));

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'documents'])
            ->with('status', 'Document uploaded.');
    }

    public function download(Matter $matter, int $document): Response
    {
        Gate::authorize('view', $matter);

        $media = $this->documentOn($matter, $document);

        $media->recordAccess('downloaded');

        return $media->toResponse(request());
    }

    public function destroy(Matter $matter, int $document): RedirectResponse
    {
        Gate::authorize('deleteDocument', $matter);

        $this->documentOn($matter, $document)->delete();

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'documents'])
            ->with('status', 'Document deleted.');
    }

    protected function documentOn(Matter $matter, int $id): Media
    {
        return $matter->media()->where('collection_name', Matter::DOCUMENTS)->findOrFail($id);
    }
}
