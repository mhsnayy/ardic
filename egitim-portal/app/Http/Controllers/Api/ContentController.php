<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderContentsRequest;
use App\Http\Requests\StoreContentRequest;
use App\Http\Resources\ContentResource;
use App\Models\Education;
use App\Models\EducationContent;
use App\Services\Content\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContentController extends Controller
{
    public function index(Education $education): AnonymousResourceCollection
    {
        return ContentResource::collection($education->contents()->with('contentable')->get());
    }

    public function store(StoreContentRequest $request, Education $education, ContentService $service): JsonResponse
    {
        $content = $service->add($education, $request->validated());

        return (new ContentResource($content))->response()->setStatusCode(201);
    }

    public function reorder(ReorderContentsRequest $request, Education $education, ContentService $service): AnonymousResourceCollection
    {
        return ContentResource::collection($service->reorder($education, $request->validated('ids')));
    }

    public function destroy(EducationContent $content, ContentService $service): Response
    {
        $service->remove($content);

        return response()->noContent();
    }
}
