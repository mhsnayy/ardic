<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EducationAssignment;
use App\Services\LearningLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LearningLinkController extends Controller
{
    public function store(EducationAssignment $assignment, LearningLinkService $service): JsonResponse
    {
        $issued = $service->issue($assignment);

        return response()->json([
            'token' => $issued['token'],
            'url' => $issued['url'],
            'expires_at' => $issued['link']->expires_at->toIso8601String(),
        ], 201);
    }

    public function destroy(EducationAssignment $assignment, LearningLinkService $service): Response
    {
        $service->revoke($assignment);

        return response()->noContent();
    }
}
