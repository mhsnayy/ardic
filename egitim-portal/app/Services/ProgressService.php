<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Models\ContentProgress;
use App\Models\EducationAssignment;
use App\Models\EducationContent;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(private LearningSummary $summary) {}

    /**
     * Records one progress report and moves the assignment forward (BR-6, BR-7, BR-8).
     *
     * @param  array<string, mixed>  $input  validated body
     * @return array{progress: ContentProgress, assignment: EducationAssignment, summary: array<string, int>}
     */
    public function record(EducationAssignment $assignment, EducationContent $content, array $input): array
    {
        return DB::transaction(function () use ($assignment, $content, $input) {
            $now = now();
            $type = $content->contentable;

            ContentProgress::firstOrCreate(['assignment_id' => $assignment->id, 'content_id' => $content->id]);
            $progress = ContentProgress::where('assignment_id', $assignment->id)
                ->where('content_id', $content->id)
                ->lockForUpdate()
                ->firstOrFail();

            $type->recordProgress($progress, $input, $now);
            $progress->last_heartbeat_at = $now;
            if ($progress->completed_at === null && $type->isCompletedBy($progress)) {
                $progress->completed_at = $now; // never undone
            }
            $progress->save();

            $assignment = EducationAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $contents = $content->education->contents()->get();
            $summary = $this->summary->summarize($contents, $this->summary->progressFor($assignment));

            $this->advance($assignment, $summary);

            return ['progress' => $progress, 'assignment' => $assignment, 'summary' => $summary];
        });
    }

    /** Automatic transitions go forward only; a completed assignment is never moved back (BR-7). */
    private function advance(EducationAssignment $assignment, array $summary): void
    {
        if ($assignment->status === AssignmentStatus::Completed) {
            return;
        }

        if ($summary['completed']) {
            $assignment->changeStatus(AssignmentStatus::Completed);
        } elseif ($assignment->status === AssignmentStatus::Assigned) {
            $assignment->changeStatus(AssignmentStatus::InProgress);
        }
    }
}
