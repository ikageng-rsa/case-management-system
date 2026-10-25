<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reference;

use App\Enums\Document\DocumentKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Reference\DocumentKindResource;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

class DocumentKindController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DocumentKindResource::collection(DocumentKind::cases());
    }
}
