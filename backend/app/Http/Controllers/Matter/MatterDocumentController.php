<?php

declare(strict_types=1);

namespace App\Http\Controllers\Matter;

use App\Enums\Document\DocumentKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\Document\Media;
use App\Models\Matter\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MatterDocumentController extends Controller
{
    public function store(StoreDocumentRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('work', $matter);

        $matter->addDocument($request->file('file'), DocumentKind::from($request->validated('kind')))
            ->recordAccess('uploaded');

        return redirect()
            ->route('matter.show', [$matter, 'tab' => 'documents'])
            ->with('status', 'Document uploaded.');
    }

    public function download(Matter $matter, int $document): Response
    {
        Gate::authorize('view', $matter);

        $media = $this->documentOn($matter, $document);

        $media->recordAccess('downloaded');

        $response = $media->toResponse(request());

        // Whatever the file is, the browser is to treat it as a download and nothing more.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
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
