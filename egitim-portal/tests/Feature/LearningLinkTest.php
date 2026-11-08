<?php

namespace Tests\Feature;

use App\Models\Education;
use App\Models\EducationAssignment;
use App\Models\LearningLink;
use App\Models\LinkContent;
use App\Models\VideoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningLinkTest extends TestCase
{
    use RefreshDatabase;

    private array $h;

    private Education $edu;

    private EducationAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        config(['portal.frontend_url' => 'http://localhost:3000/']);
        $this->h = $this->adminHeaders();
        $this->edu = Education::create(['name' => 'Eğitim', 'type' => 'safety', 'description' => 'Açıklama']);
        $this->assignment = $this->assign($this->edu, 1, 'Ayse Yilmaz');
    }

    protected function assign(Education $e, int $personnelId, string $name): EducationAssignment
    {
        return EducationAssignment::create([
            'education_id' => $e->id, 'personnel_id' => $personnelId, 'personnel_name' => $name,
            'personnel_email' => "p$personnelId@example.com", 'status' => 'assigned', 'assigned_at' => now(),
        ]);
    }

    protected function issue(?EducationAssignment $a = null): array
    {
        return $this->postJson('/api/assignments/'.($a ?? $this->assignment)->id.'/learning-link', [], $this->h)
            ->assertCreated()->json();
    }

    protected function learn(string $token)
    {
        return $this->getJson('/api/learn', ['X-Learning-Token' => $token]);
    }

    public function test_issue_returns_token_url_and_expiry_and_stores_only_hash(): void
    {
        $r = $this->issue();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $r['token']);
        $this->assertSame('http://localhost:3000/ogren/'.$r['token'], $r['url']);
        $link = LearningLink::first();
        $this->assertSame(hash('sha256', $r['token']), $link->token_hash);
        $this->assertSame($link->expires_at->toIso8601String(), $r['expires_at']);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $link->expires_at->timestamp, 5);
        $this->assertSame(0, LearningLink::where('token_hash', $r['token'])->count());
        $this->assertStringNotContainsString($r['token'], json_encode(LearningLink::all()->toArray()));
    }

    public function test_admin_endpoints_require_manage_ability(): void
    {
        $url = "/api/assignments/{$this->assignment->id}/learning-link";
        $this->postJson($url)->assertUnauthorized();
        $this->deleteJson($url, [], $this->adminHeaders(['personnel:read']))->assertForbidden();
    }

    public function test_new_link_invalidates_previous(): void
    {
        $old = $this->issue()['token'];
        $this->learn($old)->assertOk();

        $new = $this->issue()['token'];

        $this->learn($old)->assertNotFound();
        $this->learn($new)->assertOk();
        $this->assertSame(1, LearningLink::active()->count());
        $this->assertNotNull(LearningLink::where('token_hash', hash('sha256', $old))->first()->revoked_at);
    }

    public function test_revoke_and_idempotent(): void
    {
        $t = $this->issue()['token'];
        $url = "/api/assignments/{$this->assignment->id}/learning-link";

        $this->deleteJson($url, [], $this->h)->assertNoContent();
        $this->learn($t)->assertNotFound();
        $this->deleteJson($url, [], $this->h)->assertNoContent();
    }

    public function test_expired_link_is_404(): void
    {
        $t = $this->issue()['token'];
        $this->travel(31)->days();
        $this->learn($t)->assertNotFound();
    }

    public function test_all_failures_return_identical_404(): void
    {
        $t = $this->issue()['token'];
        $this->deleteJson("/api/assignments/{$this->assignment->id}/learning-link", [], $this->h);

        $expected = ['message' => 'Bu öğrenme linki geçersiz ya da süresi dolmuş.'];
        $bodies = [
            $this->getJson('/api/learn'), // missing header
            $this->learn('short'),
            $this->learn(str_repeat('a', 48)), // well-formed, unknown
            $this->learn(str_repeat('!', 48)),
            $this->learn($t), // revoked
        ];
        foreach ($bodies as $r) {
            $r->assertNotFound()->assertExactJson($expected);
        }
    }

    public function test_learn_payload_shape_and_isolation(): void
    {
        $link = LinkContent::create(['url' => 'https://example.com']);
        $video = VideoContent::create([
            'media_driver' => 'local', 'media_path' => 'media/'.$this->edu->id.'/'.'11111111-1111-1111-1111-111111111111.mp4',
            'mime_type' => 'video/mp4', 'size_bytes' => 10, 'duration_seconds' => 60,
        ]);
        $this->makeContent($this->edu, $link, 2, false, 'Bağlantı');
        $this->makeContent($this->edu, $video, 1, true, 'Video');

        $other = Education::create(['name' => 'Diğer', 'type' => 'technical']);
        $this->makeContent($other, LinkContent::create(['url' => 'https://secret.test']), 1, true, 'Gizli');
        $otherAssignment = $this->assign($other, 2, 'Mehmet');

        $mine = $this->issue()['token'];
        $r = $this->learn($mine)->assertOk()
            ->assertJsonPath('data.assignment', [
                'id' => $this->assignment->id, 'status' => 'assigned', 'status_label' => 'Atandı', 'personnel_name' => 'Ayse Yilmaz',
            ])
            ->assertJsonPath('data.education.name', 'Eğitim')
            ->assertJsonPath('data.education.type_label', 'İş Sağlığı ve Güvenliği')
            ->assertJsonPath('data.education.description', 'Açıklama')
            ->assertJsonPath('data.summary', ['required_total' => 1, 'required_completed' => 0, 'completed' => false, 'completed_count' => 0])
            ->assertJsonCount(2, 'data.contents')
            ->assertJsonPath('data.contents.0.kind', 'video')
            ->assertJsonPath('data.contents.0.sort_order', 1)
            ->assertJsonPath('data.contents.0.progress', [
                'last_position_seconds' => 0, 'max_position_seconds' => 0, 'completed' => false, 'completed_at' => null,
            ])
            ->assertJsonPath('data.contents.1.kind', 'link')
            ->assertJsonPath('data.contents.1.playback_url', null)
            ->assertJsonPath('data.contents.1.properties', ['url' => 'https://example.com']);

        $this->assertStringContainsString('/media/', $r->json('data.contents.0.playback_url'));
        $this->assertStringContainsString('signature=', $r->json('data.contents.0.playback_url'));
        $this->assertStringNotContainsString('secret.test', $r->getContent());

        // the other person's token sees only their own content
        $theirs = $this->issue($otherAssignment)['token'];
        $this->learn($theirs)->assertOk()->assertJsonCount(1, 'data.contents')
            ->assertJsonPath('data.contents.0.title', 'Gizli');
    }

    public function test_assignment_resource_exposes_active_learning_link_only(): void
    {
        $listUrl = "/api/educations/{$this->edu->id}/assignments";
        $this->getJson($listUrl, $this->h)->assertJsonPath('data.0.learning_link', null);

        $issued = $this->issue();
        $r = $this->getJson($listUrl, $this->h)->assertOk();
        $this->assertSame($issued['expires_at'], $r->json('data.0.learning_link.expires_at'));
        $this->assertNotNull($r->json('data.0.learning_link.created_at'));
        $this->assertStringNotContainsString($issued['token'], $r->getContent());
        $this->assertSame(['expires_at', 'created_at'], array_keys($r->json('data.0.learning_link')));

        $this->deleteJson("/api/assignments/{$this->assignment->id}/learning-link", [], $this->h);
        $this->getJson($listUrl, $this->h)->assertJsonPath('data.0.learning_link', null);

        $this->issue();
        $this->travel(31)->days();
        $this->getJson($listUrl, $this->h)->assertJsonPath('data.0.learning_link', null);
    }

    public function test_deleting_assignment_removes_links(): void
    {
        $t = $this->issue()['token'];
        $this->deleteJson("/api/assignments/{$this->assignment->id}", [], $this->h)->assertNoContent();

        $this->assertSame(0, LearningLink::count());
        $this->learn($t)->assertNotFound();
    }

    public function test_learn_is_rate_limited_per_token(): void
    {
        $t = $this->issue()['token'];
        for ($i = 0; $i < 120; $i++) {
            $this->learn($t)->assertOk();
        }
        $this->learn($t)->assertStatus(429);
        $this->learn(str_repeat('b', 48))->assertNotFound(); // different token, own bucket
    }

    public function test_learn_has_an_ip_wide_ceiling_across_tokens(): void
    {
        for ($i = 0; $i < 600; $i++) {
            $this->learn(str_pad((string) $i, 48, 'x'))->assertNotFound();
        }
        $this->learn(str_repeat('z', 48))->assertStatus(429);
    }
}
