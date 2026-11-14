<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Diary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Diary\ListUserDiaryEntriesRequest;
use App\Http\Resources\Diary\DiaryEntryResource;
use App\Queries\Diary\UserDiary;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

class UserDiaryEntryController extends Controller
{
    /** The user's own diary across their matters: the app's "my day". Each entry brings its matter. */
    public function index(ListUserDiaryEntriesRequest $request, UserDiary $diary): AnonymousResourceCollection
    {
        $diary = $diary->for($request->user(), $request->show(), $request->from(), $request->to());

        return DiaryEntryResource::collection($diary['entries']->withQueryString())
            ->additional(['meta' => ['counts' => $diary['counts']]]);
    }
}
