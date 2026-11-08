<?php

namespace App\Services;

use App\Models\ContentProgress;
use App\Models\EducationAssignment;
use App\Models\EducationContent;
use Illuminate\Support\Collection;

/** Progress numbers of one assignment. */
class LearningSummary
{
    /**
     * @param  Collection<int, EducationContent>  $contents  with contentable loaded
     * @param  Collection<int, ContentProgress>  $progress  keyed by content_id
     * @return array{required_total: int, required_completed: int, completed: bool, completed_count: int}
     */
    public function summarize(Collection $contents, Collection $progress): array
    {
        $done = fn (EducationContent $c) => $progress->get($c->id)?->completed_at !== null;
        $required = $contents->where('is_required', true);

        $requiredTotal = $required->count();
        $requiredCompleted = $required->filter($done)->count();
        $completedCount = $contents->filter($done)->count();

        // Single definition of "the assignment is now complete"; ProgressService uses the same flag.
        // With no required content, at least one (optional) content must have been completed.
        $completed = $contents->isNotEmpty()
            && $requiredCompleted >= $requiredTotal
            && ($requiredTotal > 0 || $completedCount > 0);

        return [
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
            'completed' => $completed,
            'completed_count' => $completedCount,
        ];
    }

    /** @return Collection<int, ContentProgress> keyed by content_id */
    public function progressFor(EducationAssignment $assignment): Collection
    {
        return ContentProgress::where('assignment_id', $assignment->id)->get()->keyBy('content_id');
    }
}
