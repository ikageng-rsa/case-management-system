<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Search\SiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SiteSearch $search): JsonResponse
    {
        $term = is_string($request->query('q')) ? $request->query('q') : '';

        return response()->json(['groups' => $search->for($request->user(), $term)]);
    }
}
