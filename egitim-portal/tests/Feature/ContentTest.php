<?php

namespace Tests\Feature;

use App\Contracts\ContentType;
use App\Models\ContentProgress;
use App\Models\DocumentContent;
use App\Models\Education;
use App\Models\EducationContent;
use App\Models\LinkContent;
use App\Models\VideoContent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    private array $h;

    private Education $edu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->h = $this->adminHeaders();
        $this->edu = Education::create(['name' => 'Eğitim', 'type' => 'technical']);
    }

    private function url(?Education $e = null): string
    {
        return '/api/educations/'.($e ?? $this->edu)->id.'/contents';
    }

    private function addLink(string $title = 'Link', ?Education $e = null): int
    {
        return $this->postJson($this->url($e), [
            'type' => 'link', 'title' => $title, 'is_required' => true, 'url' => 'https://example.com/'.$title,
        ], $this->h)->assertCreated()->json('data.id');
    }

    public function test_add_link_returns_contract_shape(): void
    {
        $this->postJson($this->url(), [
            'type' => 'link', 'title' => 'Doküman', 'is_required' => false, 'url' => 'https://example.com/a',
        ], $this->h)
            ->assertCreated()
            ->assertJsonPath('data.kind', 'link')
            ->assertJsonPath('data.education_id', $this->edu->id)
            ->assertJsonPath('data.title', 'Doküman')
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.properties', ['url' => 'https://example.com/a'])
            ->assertJsonStructure(['data' => ['id', 'created_at']]);
    }

    public function test_is_required_defaults_to_true_and_sort_order_appends(): void
    {
        $this->postJson($this->url(), ['type' => 'link', 'title' => 'A', 'url' => 'https://a.test'], $this->h)
            ->assertCreated()->assertJsonPath('data.is_required', true);
        $this->postJson($this->url(), ['type' => 'link', 'title' => 'B', 'url' => 'https://b.test'], $this->h)
            ->assertJsonPath('data.sort_order', 2);
    }

    public function test_index_lists_by_sort_order(): void
    {
        $a = $this->addLink('A');
        $b = $this->addLink('B');
        EducationContent::whereKey($a)->update(['sort_order' => 2]);
        EducationContent::whereKey($b)->update(['sort_order' => 1]);

        $this->getJson($this->url(), $this->h)->assertOk()
            ->assertJsonPath('data.0.id', $b)->assertJsonPath('data.1.id', $a);
    }

    public function test_validation_errors(): void
    {
        $this->postJson($this->url(), [], $this->h)->assertUnprocessable()->assertJsonValidationErrors(['type', 'title']);
        $this->postJson($this->url(), ['type' => 'x', 'title' => 'T'], $this->h)->assertJsonValidationErrors(['type']);
        $this->postJson($this->url(), ['type' => 'link', 'title' => str_repeat('a', 151), 'url' => 'https://a.test'], $this->h)
            ->assertJsonValidationErrors(['title']);
        $this->postJson($this->url(), ['type' => 'link', 'title' => 'T'], $this->h)->assertJsonValidationErrors(['url']);
        $this->postJson($this->url(), ['type' => 'link', 'title' => 'T', 'url' => 'javascript:alert(1)'], $this->h)
            ->assertJsonValidationErrors(['url']);
        $this->postJson($this->url(), ['type' => 'link', 'title' => 'T', 'url' => 'ftp://a.test/x'], $this->h)
            ->assertJsonValidationErrors(['url']);
        $this->postJson($this->url(), ['type' => 'link', 'title' => 'T', 'url' => 'https://a.test/'.str_repeat('a', 2000)], $this->h)
            ->assertJsonValidationErrors(['url']);
        $this->postJson($this->url(), ['type' => 'link', 'title' => 'T', 'url' => 'https://a.test', 'is_required' => 'maybe'], $this->h)
            ->assertJsonValidationErrors(['is_required']);
        $this->assertSame(0, EducationContent::count());
    }

    public function test_video_and_document_not_yet_supported(): void
    {
        foreach (['video', 'document'] as $type) {
            $this->postJson($this->url(), ['type' => $type, 'title' => 'T'], $this->h)
                ->assertUnprocessable()->assertJsonValidationErrors(['type']);
        }
    }

    public function test_requires_manage_ability_and_auth(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        $this->getJson($this->url(), $this->adminHeaders(['personnel:read']))->assertForbidden();
        $this->getJson('/api/educations/abc/contents', $this->h)->assertNotFound();
        $this->getJson('/api/educations/999/contents', $this->h)->assertNotFound();
    }

    public function test_reorder(): void
    {
        $a = $this->addLink('A');
        $b = $this->addLink('B');
        $c = $this->addLink('C');

        $this->patchJson($this->url().'/order', ['ids' => [$c, $a, $b]], $this->h)->assertOk()
            ->assertJsonPath('data.0.id', $c)->assertJsonPath('data.0.sort_order', 1)
            ->assertJsonPath('data.1.id', $a)->assertJsonPath('data.2.id', $b)->assertJsonPath('data.2.sort_order', 3);
    }

    public function test_reorder_rejects_missing_duplicate_and_foreign_ids(): void
    {
        $a = $this->addLink('A');
        $b = $this->addLink('B');
        $other = Education::create(['name' => 'Diğer', 'type' => 'technical']);
        $foreign = $this->addLink('X', $other);

        $this->patchJson($this->url().'/order', ['ids' => [$a]], $this->h)->assertUnprocessable()->assertJsonValidationErrors(['ids']);
        $this->patchJson($this->url().'/order', ['ids' => [$a, $a]], $this->h)->assertUnprocessable();
        $this->patchJson($this->url().'/order', ['ids' => [$a, $b, $foreign]], $this->h)->assertUnprocessable();
        $this->patchJson($this->url().'/order', ['ids' => [$a, $foreign]], $this->h)->assertUnprocessable();
        $this->patchJson($this->url().'/order', [], $this->h)->assertUnprocessable();
        $this->patchJson($this->url().'/order', ['ids' => ['x', 'y']], $this->h)->assertUnprocessable();
        $this->assertSame(1, EducationContent::find($a)->sort_order);
    }

    public function test_delete_renumbers_and_removes_contentable(): void
    {
        $a = $this->addLink('A');
        $b = $this->addLink('B');
        $c = $this->addLink('C');
        $linkId = EducationContent::find($b)->contentable_id;

        $this->deleteJson("/api/contents/$b", [], $this->h)->assertNoContent();

        $this->assertNull(LinkContent::find($linkId));
        $this->assertSame(1, EducationContent::find($a)->sort_order);
        $this->assertSame(2, EducationContent::find($c)->sort_order);
        $this->deleteJson('/api/contents/999', [], $this->h)->assertNotFound();
    }

    public function test_deleting_education_cascades_contents_and_contentables(): void
    {
        $this->addLink('A');
        $this->addLink('B');
        $other = Education::create(['name' => 'Diğer', 'type' => 'technical']);
        $this->addLink('keep', $other);

        $this->deleteJson("/api/educations/{$this->edu->id}", [], $this->h)->assertNoContent();

        $this->assertSame(1, EducationContent::count());
        $this->assertSame(1, LinkContent::count());
    }

    public function test_education_resource_has_contents_count(): void
    {
        $this->addLink('A');
        $this->addLink('B');

        $this->getJson("/api/educations/{$this->edu->id}", $this->h)->assertJsonPath('data.contents_count', 2);
        $this->getJson('/api/educations', $this->h)->assertJsonPath('data.0.contents_count', 2);
    }

    public function test_morph_map_stores_short_alias(): void
    {
        $id = $this->addLink('A');

        $this->assertSame('link', DB::table('education_contents')->where('id', $id)->value('contentable_type'));
        $this->assertInstanceOf(LinkContent::class, EducationContent::find($id)->contentable);
    }

    public function test_content_progress_unique_per_assignment_and_content(): void
    {
        $contentId = $this->addLink('A');
        $assignmentId = DB::table('education_assignments')->insertGetId([
            'education_id' => $this->edu->id, 'personnel_id' => 1, 'personnel_name' => 'A B',
            'personnel_email' => 'a@b.c', 'status' => 'assigned', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        ContentProgress::create(['assignment_id' => $assignmentId, 'content_id' => $contentId]);

        $this->assertSame(0, ContentProgress::first()->max_position_seconds);
        $this->expectException(UniqueConstraintViolationException::class);
        ContentProgress::create(['assignment_id' => $assignmentId, 'content_id' => $contentId]);
    }

    private function progress(int $max = 0, bool $completed = false): ContentProgress
    {
        return new ContentProgress([
            'max_position_seconds' => $max,
            'completed_at' => $completed ? now() : null,
        ]);
    }

    public function test_video_completion_threshold(): void
    {
        $v = new VideoContent(['duration_seconds' => 100]);
        $this->assertFalse($v->isCompletedBy($this->progress(89)));
        $this->assertTrue($v->isCompletedBy($this->progress(90)));
        $this->assertTrue($v->isCompletedBy($this->progress(100)));

        $odd = new VideoContent(['duration_seconds' => 101]); // ceil(90.9) = 91
        $this->assertFalse($odd->isCompletedBy($this->progress(90)));
        $this->assertTrue($odd->isCompletedBy($this->progress(91)));
    }

    public function test_video_without_duration_never_completes(): void
    {
        $this->assertFalse((new VideoContent(['duration_seconds' => null]))->isCompletedBy($this->progress(9999, true)));
    }

    public function test_document_and_link_complete_when_completed_at_set(): void
    {
        foreach ([new DocumentContent, new LinkContent] as $c) {
            $this->assertFalse($c->isCompletedBy($this->progress(0, false)));
            $this->assertTrue($c->isCompletedBy($this->progress(0, true)));
        }
    }

    public function test_kinds_and_properties(): void
    {
        $types = [
            'video' => new VideoContent(['mime_type' => 'video/mp4', 'size_bytes' => 5, 'duration_seconds' => 9]),
            'document' => new DocumentContent(['mime_type' => 'application/pdf', 'size_bytes' => 7]),
            'link' => new LinkContent(['url' => 'https://a.test']),
        ];
        foreach ($types as $kind => $m) {
            $this->assertInstanceOf(ContentType::class, $m);
            $this->assertSame($kind, $m->kind());
        }
        $this->assertSame(['mime_type' => 'video/mp4', 'size_bytes' => 5, 'duration_seconds' => 9], $types['video']->toContentProperties());
        $this->assertSame(['mime_type' => 'application/pdf', 'size_bytes' => 7], $types['document']->toContentProperties());
    }
}
