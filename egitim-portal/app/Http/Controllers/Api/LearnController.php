<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgressRequest;
use App\Media\MediaManager;
use App\Models\EducationAssignment;
use App\Services\LearningSummary;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnController extends Controller
{
    public function progress(ProgressRequest $request, ProgressService $service): JsonResponse
    {
        $result = $service->record(
            $request->attributes->get('assignment'),
            $request->learnContent(),
            $request->validated(),
        );
        $p = $result['progress'];
        $status = $result['assignment']->status;

        return response()->json(['data' => [
            'content_id' => $p->content_id,
            'last_position_seconds' => $p->last_position_seconds,
            'max_position_seconds' => $p->max_position_seconds,
            'completed' => $p->completed_at !== null,
            'assignment_status' => $status->value,
            'assignment_status_label' => $status->label(),
            'summary' => $result['summary'],
        ]]);
    }

    public function show(Request $request, MediaManager $media, LearningSummary $summary): JsonResponse
    {
        /** @var EducationAssignment $assignment */
        $assignment = $request->attributes->get('assignment');
        $education = $assignment->education;
        $contents = $education->contents()->with('contentable')->get();
        $progress = $summary->progressFor($assignment);
        $exp = now()->addSeconds((int) config('media.playback_ttl'));

        return response()->json(['data' => [
            'assignment' => [
                'id' => $assignment->id,
                'status' => $assignment->status->value,
                'status_label' => $assignment->status->label(),
                'personnel_name' => $assignment->personnel_name,
            ],
            'education' => [
                'id' => $education->id,
                'name' => $education->name,
                'type_label' => $education->type->label(),
                'description' => $education->description,
            ],
            'contents' => $contents->map(function ($content) use ($progress, $media, $exp) {
                $p = $progress->get($content->id);

                return [
                    'id' => $content->id,
                    'kind' => $content->contentable->kind(),
                    'title' => $content->title,
                    'is_required' => $content->is_required,
                    'sort_order' => $content->sort_order,
                    'properties' => $content->contentable->toContentProperties(),
                    'playback_url' => $content->contentable->playbackUrl($media, $exp),
                    'progress' => [
                        'last_position_seconds' => $p?->last_position_seconds ?? 0,
                        'max_position_seconds' => $p?->max_position_seconds ?? 0,
                        'completed' => $p?->completed_at !== null,
                        'completed_at' => $p?->completed_at?->toIso8601String(),
                    ],
                ];
            })->values(),
            'summary' => $summary->summarize($contents, $progress),
        ]]);
    }
}
