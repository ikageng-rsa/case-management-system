<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\Client\FicaDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreFicaDocumentRequest;
use App\Models\Client\Client;
use App\Models\Document\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClientFicaDocumentController extends Controller
{
    public function store(StoreFicaDocumentRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->addFicaDocument($request->file('file'), FicaDocument::from($request->validated('document')))
            ->recordAccess('uploaded');

        return redirect()->route('client.show', $client)->with('status', 'FICA document uploaded.');
    }

    public function download(Request $request, Client $client, int $document): Response
    {
        Gate::authorize('downloadFicaDocument', $client);

        $media = $this->documentOn($client, $document);

        $media->recordAccess('downloaded');

        $response = $media->toResponse($request);

        // Whatever the file is, the browser is to treat it as a download and nothing more.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function destroy(Client $client, int $document): RedirectResponse
    {
        Gate::authorize('deleteFicaDocument', $client);

        $this->documentOn($client, $document)->delete();

        return redirect()->route('client.show', $client)->with('status', 'FICA document deleted.');
    }

    protected function documentOn(Client $client, int $id): Media
    {
        return $client->media()->where('collection_name', Client::FICA)->findOrFail($id);
    }
}
