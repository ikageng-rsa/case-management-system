<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Document\ListDocumentsRequest;
use App\Http\Requests\Api\Document\StoreDocumentRequest;
use App\Http\Resources\Document\DocumentResource;
use App\Models\Document\Media;
use App\Models\Matter\Matter;
use App\Queries\Matters\MatterDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MatterDocumentController extends Controller
{
    private const PER_PAGE = 25;

    /** The matter's documents, newest first, a page at a time, with how many of each kind there are. */
    public function index(ListDocumentsRequest $request, Matter $matter, MatterDocuments $documents): AnonymousResourceCollection
    {
        abort_unless(Gate::allows('view', $matter), 404);

        $documents = $documents->for($matter, $request->kind());
        $page = Paginator::resolveCurrentPage();

        $paginated = new LengthAwarePaginator(
            $documents['documents']->forPage($page, self::PER_PAGE)->values(),
            $documents['documents']->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return DocumentResource::collection($paginated)->additional(['meta' => [
            'counts' => [
                'all' => $documents['allDocumentsCount'],
                'kinds' => $documents['kindCounts'],
            ],
        ]]);
    }

    /** The file itself, as a download whatever it is. Every download is logged. */
    public function show(Request $request, Matter $matter, int $document): Response
    {
        abort_unless(Gate::allows('view', $matter), 404);

        $media = $this->documentOn($matter, $document);

        $media->recordAccess('downloaded', ['ip' => $request->ip(), 'via' => 'api']);

        $response = $media->toResponse($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** File a document on the matter. Only those carrying an open matter may. */
    public function store(StoreDocumentRequest $request, Matter $matter): JsonResponse
    {
        abort_unless(Gate::allows('view', $matter), 404);
        Gate::authorize('work', $matter);

        $media = $matter->addDocument($request->file('file'), $request->kind(), $request->name());
        $media->recordAccess('uploaded', ['via' => 'api']);

        return (new DocumentResource($media))->response()->setStatusCode(201);
    }

    private function documentOn(Matter $matter, int $id): Media
    {
        return $matter->media()->where('collection_name', Matter::DOCUMENTS)->findOrFail($id);
    }
}
