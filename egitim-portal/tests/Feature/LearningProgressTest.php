<?php

namespace Tests\Feature;

use App\Models\ContentProgress;
use App\Models\DocumentContent;
use App\Models\Education;
use App\Models\EducationAssignment;
use App\Models\EducationContent;
use App\Models\LinkContent;
use App\Models\VideoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningProgressTest extends TestCase
{
    use RefreshDatabase;

    private array $h;

    private Education $edu;

    private EducationAssignment $assignment;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Carbon::setTestNow('2026-01-01 10:00:00');
        $this->h = $this->adminHeaders();
        $this->edu = Education::create(['name' => 'Eğitim', 'type' => 'technical']);
        $this->assignment = $this->newAssignment($this->edu, 1);
        $this->token = $this->tokenFor($this->assignment);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function newAssignment(Education $e, int $pid): EducationAssignment
    {
        return EducationAssignment::create([
            'education_id' => $e->id, 'personnel_id' => $pid, 'personnel_name' => "Kişi $pid",
            'personnel_email' => "p$pid@example.com", 'status' => 'assigned', 'assigned_at' => now(),
        ]);
    }

    private function tokenFor(EducationAssignment $a): string
    {
        return $this->postJson("/api/assignments/{$a->id}/learning-link", [], $this->h)->json('token');
    }

    private function video(?int $duration = 100, bool $required = true, int $order = 1, ?Education $e = null): EducationContent
    {
        $v = VideoContent::create([
            'media_driver' => 'local', 'media_path' => 'media/1/11111111-1111-1111-1111-111111111111.mp4',
            'mime_type' => 'video/mp4', 'size_bytes' => 10, 'duration_seconds' => $duration,
        ]);

        return $this->makeContent($e ?? $this->edu, $v, $order, $required, 'Video');
    }

    private function pdf(bool $required = true, int $order = 2): EducationContent
    {
        return $this->makeContent($this->edu, DocumentContent::create([
            'media_driver' => 'local', 'media_path' => 'media/1/22222222-2222-2222-2222-222222222222.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 5,
        ]), $order, $required, 'PDF');
    }

    private function link(bool $required = true, int $order = 3): EducationContent
    {
        return $this->makeContent($this->edu, LinkContent::create(['url' => 'https://example.com']), $order, $required, 'Link');
    }

    private function report(EducationContent $c, array $body = [], ?string $token = null)
    {
        return $this->postJson("/api/learn/contents/{$c->id}/progress", $body, ['X-Learning-Token' => $token ?? $this->token]);
    }

    private function beat(EducationContent $c, float $pos)
    {
        return $this->report($c, ['position' => $pos]);
    }

    private function currentStatus(): string
    {
        return $this->assignment->fresh()->status->value;
    }

    // ---- BR-8

    public function test_first_heartbeat_accepts_at_most_five_seconds(): void
    {
        $v = $this->video();
        $this->beat($v, 500)->assertOk()->assertJsonPath('data.max_position_seconds', 5);
    }

    public function test_forward_seek_is_capped_by_elapsed_time(): void
    {
        $v = $this->video();
        $this->beat($v, 0)->assertOk();
        Carbon::setTestNow(now()->addSeconds(10));

        $this->beat($v, 500)->assertOk()
            ->assertJsonPath('data.last_position_seconds', 20)
            ->assertJsonPath('data.max_position_seconds', 20)
            ->assertJsonPath('data.completed', false);
        $this->assertSame('in_progress', $this->currentStatus());
    }

    public function test_rewind_is_free_and_max_position_never_decreases(): void
    {
        $v = $this->video();
        $this->beat($v, 0);
        Carbon::setTestNow(now()->addSeconds(10));
        $this->beat($v, 20)->assertJsonPath('data.max_position_seconds', 20);
        Carbon::setTestNow(now()->addSeconds(10));

        $this->beat($v, 3)->assertOk()
            ->assertJsonPath('data.last_position_seconds', 3)
            ->assertJsonPath('data.max_position_seconds', 20);
    }

    public function test_position_is_clamped_to_duration(): void
    {
        $v = $this->video(30);
        $this->beat($v, 0);
        Carbon::setTestNow(now()->addSeconds(60));

        $this->beat($v, 9999)->assertJsonPath('data.max_position_seconds', 30);
    }

    public function test_rapid_heartbeats_cannot_accumulate_tolerance(): void
    {
        $v = $this->video(1000);
        for ($i = 0; $i < 100; $i++) {
            $this->beat($v, 999);
        }
        $this->assertLessThanOrEqual(5, ContentProgress::first()->max_position_seconds);
    }

    public function test_waiting_does_not_bank_seek_allowance(): void
    {
        $v = $this->video(10000);
        $this->beat($v, 0);
        Carbon::setTestNow(now()->addMinutes(10));

        $this->beat($v, 9000)->assertJsonPath('data.max_position_seconds', 120);
    }

    public function test_watching_normally_to_90_percent_completes_video_and_assignment(): void
    {
        $v = $this->video(100);
        $this->beat($v, 0);
        $r = null;
        foreach ([10, 20, 30, 40, 50, 60, 70, 80, 89] as $pos) {
            Carbon::setTestNow(now()->addSeconds(10));
            $r = $this->beat($v, $pos)->assertJsonPath('data.completed', false);
        }
        $this->assertSame('in_progress', $this->currentStatus());

        Carbon::setTestNow(now()->addSeconds(10));
        $this->beat($v, 90)->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.assignment_status', 'completed')
            ->assertJsonPath('data.assignment_status_label', 'Tamamlandı')
            ->assertJsonPath('data.summary', ['required_total' => 1, 'required_completed' => 1, 'completed' => true, 'completed_count' => 1]);

        $a = $this->assignment->fresh();
        $this->assertNotNull($a->completed_at);
        $this->assertNotNull(ContentProgress::first()->completed_at);
    }

    public function test_skipping_ahead_cannot_complete_video(): void
    {
        $v = $this->video(100);
        $this->beat($v, 0);
        Carbon::setTestNow(now()->addSeconds(10));
        $this->beat($v, 100)->assertJsonPath('data.completed', false);
        $this->assertSame('in_progress', $this->currentStatus());
    }

    // ---- duration is admin-supplied (Y1)

    public function test_client_duration_is_ignored_and_cannot_complete_early(): void
    {
        $v = $this->video(100);
        $this->report($v, ['position' => 999, 'duration' => 5])->assertOk()
            ->assertJsonPath('data.completed', false)
            ->assertJsonPath('data.max_position_seconds', 5);
        $this->assertSame(100, $v->contentable->fresh()->duration_seconds);
        $this->assertSame('in_progress', $this->currentStatus());

        Carbon::setTestNow(now()->addSeconds(10));
        $this->report($v, ['position' => 20, 'duration' => 86400])->assertOk();
        $this->assertSame(100, $v->contentable->fresh()->duration_seconds);
    }

    public function test_duration_is_not_required_and_unknown_duration_never_completes(): void
    {
        $v = $this->video(null);
        $this->report($v, ['position' => 1])->assertOk();
        $this->report($v, ['position' => 3, 'duration' => 10])->assertOk();
        Carbon::setTestNow(now()->addSeconds(10));
        $this->report($v, ['position' => 9, 'duration' => 10])->assertOk()
            ->assertJsonPath('data.completed', false);
        $this->assertNull($v->contentable->fresh()->duration_seconds);
    }

    // ---- BR-6 documents and links

    public function test_opening_pdf_and_clicking_link_complete_them(): void
    {
        $pdf = $this->pdf();
        $link = $this->link();

        $this->report($pdf)->assertOk()->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.assignment_status', 'in_progress');
        $this->report($link)->assertOk()->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.assignment_status', 'completed');
    }

    // ---- BR-7

    public function test_first_progress_sets_in_progress(): void
    {
        $v = $this->video();
        $this->link(true);
        $this->assertSame('assigned', $this->currentStatus());

        $this->beat($v, 0)->assertJsonPath('data.assignment_status', 'in_progress');
        $this->assertSame('in_progress', $this->currentStatus());
        $this->assertNull($this->assignment->fresh()->completed_at);
    }

    public function test_assignment_completes_only_when_all_required_done(): void
    {
        $v = $this->video(100, true, 1);
        $pdf = $this->pdf(true, 2);

        $this->beat($v, 0);
        Carbon::setTestNow(now()->addSeconds(60));
        $this->beat($v, 90);
        // video alone is not enough
        $this->assertSame('in_progress', $this->currentStatus());
        $this->getJson('/api/learn', ['X-Learning-Token' => $this->token])
            ->assertJsonPath('data.summary', ['required_total' => 2, 'required_completed' => 1, 'completed' => false, 'completed_count' => 1]);

        $this->report($pdf)->assertJsonPath('data.assignment_status', 'completed');
    }

    public function test_optional_content_is_not_awaited(): void
    {
        $pdf = $this->pdf(true, 1);
        $this->link(false, 2);

        $this->report($pdf)->assertJsonPath('data.assignment_status', 'completed')
            ->assertJsonPath('data.summary', ['required_total' => 1, 'required_completed' => 1, 'completed' => true, 'completed_count' => 1]);
    }

    public function test_summary_completed_is_boolean_and_matches_assignment_completion(): void
    {
        $h = ['X-Learning-Token' => $this->token];
        // empty education and untouched optional-only education are not "completed"
        $this->getJson('/api/learn', $h)->assertJsonPath('data.summary.completed', false);
        $link = $this->link(false, 1);
        $this->getJson('/api/learn', $h)->assertJsonPath('data.summary.completed', false)
            ->assertJsonPath('data.summary.completed_count', 0);

        $this->report($link)->assertJsonPath('data.summary.completed', true)
            ->assertJsonPath('data.assignment_status', 'completed');
        $this->getJson('/api/learn', $h)->assertJsonPath('data.summary.completed', true);
    }

    public function test_summary_completed_false_while_required_pending(): void
    {
        $this->pdf(true, 1);
        $link = $this->link(false, 2);

        $this->report($link)->assertJsonPath('data.summary.completed', false)
            ->assertJsonPath('data.summary.completed_count', 1);
    }

    public function test_optional_progress_alone_does_not_complete_when_required_pending(): void
    {
        $this->pdf(true, 1);
        $link = $this->link(false, 2);

        $this->report($link)->assertJsonPath('data.assignment_status', 'in_progress');
    }

    public function test_admin_can_reset_and_progress_moves_forward_again(): void
    {
        $pdf = $this->pdf(true, 1);
        $link = $this->link(true, 2);
        $this->report($pdf);
        $this->report($link);
        $this->assertSame('completed', $this->currentStatus());

        $this->patchJson("/api/assignments/{$this->assignment->id}", ['status' => 'assigned'], $this->h)->assertOk();
        $this->assertSame('assigned', $this->currentStatus());
        $this->assertNull($this->assignment->fresh()->completed_at);

        // contents are still done, so the next report moves it forward (all required complete) again
        $this->report($pdf)->assertOk();
        $this->assertSame('completed', $this->currentStatus());
    }

    public function test_reset_to_assigned_then_partial_progress_gives_in_progress(): void
    {
        $v = $this->video();
        $this->pdf(true, 2);
        $this->patchJson("/api/assignments/{$this->assignment->id}", ['status' => 'assigned'], $this->h)->assertOk();

        $this->beat($v, 0)->assertJsonPath('data.assignment_status', 'in_progress');
    }

    public function test_completed_assignment_is_never_moved_back_by_progress(): void
    {
        $v = $this->video();
        $this->pdf(true, 2);
        $this->patchJson("/api/assignments/{$this->assignment->id}", ['status' => 'completed'], $this->h)->assertOk();
        $completedAt = $this->assignment->fresh()->completed_at;

        $this->beat($v, 0)->assertOk()->assertJsonPath('data.assignment_status', 'completed');
        $this->assertSame('completed', $this->currentStatus());
        $this->assertEquals($completedAt, $this->assignment->fresh()->completed_at);
    }

    public function test_content_completion_is_never_undone(): void
    {
        $v = $this->video(100);
        $this->pdf(true, 2);
        $this->beat($v, 0);
        Carbon::setTestNow(now()->addSeconds(60));
        $this->beat($v, 90)->assertJsonPath('data.completed', true);
        Carbon::setTestNow(now()->addSeconds(10));

        $this->beat($v, 0)->assertJsonPath('data.completed', true);
    }

    // ---- access and validation

    public function test_foreign_content_is_404(): void
    {
        $other = Education::create(['name' => 'Diğer', 'type' => 'technical']);
        $foreign = $this->video(100, true, 1, $other);

        $this->beat($foreign, 0)->assertNotFound();
        $this->assertSame(0, ContentProgress::count());
        $this->postJson('/api/learn/contents/99999/progress', [], ['X-Learning-Token' => $this->token])->assertNotFound();
    }

    public function test_invalid_missing_or_revoked_token_is_404(): void
    {
        $v = $this->video();
        $expected = ['message' => 'Bu öğrenme linki geçersiz ya da süresi dolmuş.'];

        $this->postJson("/api/learn/contents/{$v->id}/progress", ['position' => 0, 'duration' => 10])
            ->assertNotFound()->assertExactJson($expected);
        $this->beat($v, 0)->assertOk();
        $this->report($v, ['position' => 0, 'duration' => 10], str_repeat('x', 48))->assertNotFound()->assertExactJson($expected);

        $this->deleteJson("/api/assignments/{$this->assignment->id}/learning-link", [], $this->h);
        $this->beat($v, 0)->assertNotFound()->assertExactJson($expected);
    }

    public function test_token_of_another_assignment_cannot_report_for_this_one(): void
    {
        $v = $this->video();
        $other = $this->newAssignment($this->edu, 2);
        $otherToken = $this->tokenFor($other);

        $this->report($v, ['position' => 0, 'duration' => 10], $otherToken)->assertOk();
        $this->assertSame(0, ContentProgress::where('assignment_id', $this->assignment->id)->count());
        $this->assertSame(1, ContentProgress::where('assignment_id', $other->id)->count());
    }

    public function test_video_body_validation(): void
    {
        $v = $this->video();
        $this->report($v, [])->assertUnprocessable()->assertJsonValidationErrors(['position']);
        $this->report($v, ['position' => -1, 'duration' => 10])->assertUnprocessable()->assertJsonValidationErrors(['position']);
        $this->report($v, ['position' => 'abc', 'duration' => 10])->assertUnprocessable()->assertJsonValidationErrors(['position']);
        $this->assertSame(0, ContentProgress::count());
        $this->assertSame('assigned', $this->currentStatus());
    }

    public function test_document_and_link_accept_empty_body(): void
    {
        $this->report($this->pdf())->assertOk();
        $this->report($this->link())->assertOk();
    }

    public function test_repeated_report_updates_single_progress_row(): void
    {
        $pdf = $this->pdf();
        $this->report($pdf);
        $this->report($pdf);

        $this->assertSame(1, ContentProgress::count());
    }

    public function test_learn_view_reflects_progress(): void
    {
        $v = $this->video(100);
        $this->beat($v, 0);
        Carbon::setTestNow(now()->addSeconds(10));
        $this->beat($v, 12);

        $this->getJson('/api/learn', ['X-Learning-Token' => $this->token])->assertOk()
            ->assertJsonPath('data.assignment.status', 'in_progress')
            ->assertJsonPath('data.contents.0.progress.last_position_seconds', 12)
            ->assertJsonPath('data.contents.0.progress.max_position_seconds', 12)
            ->assertJsonPath('data.contents.0.progress.completed', false);
    }
}
