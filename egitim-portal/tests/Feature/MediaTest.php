<?php

namespace Tests\Feature;

use App\Media\MediaManager;
use App\Models\DocumentContent;
use App\Models\Education;
use App\Models\EducationAssignment;
use App\Models\EducationContent;
use App\Models\MediaUpload;
use App\Models\VideoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    private array $h;

    private Education $edu;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        config(['cors.allowed_origins' => ['http://localhost:3000']]);
        $this->h = $this->adminHeaders();
        $this->edu = Education::create(['name' => 'Eğitim', 'type' => 'technical']);
    }

    private function target(string $kind = 'video', string $mime = 'video/mp4', int $size = 1000, ?Education $e = null)
    {
        return $this->postJson('/api/educations/'.($e ?? $this->edu)->id.'/uploads', [
            'kind' => $kind, 'filename' => 'x.'.Str::after($mime, '/'), 'mime_type' => $mime, 'size_bytes' => $size,
        ], $this->h);
    }

    private function sendRaw(string $url, string $body, string $mime = 'video/mp4', array $server = [])
    {
        return $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => $mime] + $server, $body);
    }

    /** @return array{0: string, 1: string} upload id and path */
    private function uploaded(string $kind = 'video', string $mime = 'video/mp4', string $body = 'DATA', ?Education $e = null): array
    {
        $r = $this->target($kind, $mime, strlen($body), $e)->assertCreated();
        $this->sendRaw($r->json('upload_url'), $body, $mime)->assertNoContent();

        return [$r->json('upload_id'), MediaUpload::find($r->json('upload_id'))->path];
    }

    // ---- upload target

    public function test_upload_target_contract(): void
    {
        $r = $this->target('video', 'video/mp4', 5000)->assertCreated()
            ->assertJsonPath('method', 'PUT')
            ->assertJsonPath('headers', ['Content-Type' => 'video/mp4'])
            ->assertJsonPath('max_bytes', 209715200)
            ->assertJsonStructure(['upload_id', 'upload_url', 'expires_at']);

        $this->assertStringContainsString('/uploads/'.$r->json('upload_id'), $r->json('upload_url'));
        $this->assertStringContainsString('signature=', $r->json('upload_url'));
        $this->assertStringNotContainsString('/api/', $r->json('upload_url'));

        $u = MediaUpload::find($r->json('upload_id'));
        $this->assertSame('pending', $u->status);
        $this->assertSame('local', $u->media_driver);
        $this->assertSame("media/{$this->edu->id}/{$u->id}.mp4", $u->path);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $u->expires_at->timestamp, 5);
        $this->assertSame($u->expires_at->toIso8601String(), $r->json('expires_at'));
    }

    public function test_upload_target_limits_per_kind(): void
    {
        $this->target('video', 'video/webm', 209715200)->assertCreated();
        $this->target('document', 'application/pdf', 52428800)->assertCreated()->assertJsonPath('max_bytes', 52428800);

        $this->target('video', 'video/mp4', 209715201)->assertUnprocessable()->assertJsonValidationErrors(['size_bytes']);
        $this->target('document', 'application/pdf', 52428801)->assertUnprocessable()->assertJsonValidationErrors(['size_bytes']);
        $this->target('video', 'video/quicktime')->assertUnprocessable()->assertJsonValidationErrors(['mime_type']);
        $this->target('document', 'video/mp4')->assertUnprocessable()->assertJsonValidationErrors(['mime_type']);
        $this->target('image', 'image/png')->assertUnprocessable()->assertJsonValidationErrors(['kind']);
        $this->target('video', 'video/mp4', 0)->assertUnprocessable()->assertJsonValidationErrors(['size_bytes']);
        $this->postJson("/api/educations/{$this->edu->id}/uploads", [], $this->h)->assertUnprocessable();
    }

    public function test_upload_target_requires_admin_token(): void
    {
        $this->postJson("/api/educations/{$this->edu->id}/uploads", [])->assertUnauthorized();
        $this->postJson("/api/educations/{$this->edu->id}/uploads", [], $this->adminHeaders(['personnel:read']))->assertForbidden();
    }

    public function test_filename_never_reaches_the_path(): void
    {
        $r = $this->postJson("/api/educations/{$this->edu->id}/uploads", [
            'kind' => 'document', 'filename' => '../../etc/passwd.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10,
        ], $this->h)->assertCreated();

        $this->assertStringNotContainsString('passwd', MediaUpload::find($r->json('upload_id'))->path);
    }

    // ---- receiving the PUT

    public function test_signed_put_stores_raw_body(): void
    {
        $r = $this->target('video', 'video/mp4', 4)->assertCreated();
        $this->sendRaw($r->json('upload_url'), 'DATA')->assertNoContent();

        $u = MediaUpload::find($r->json('upload_id'));
        $this->assertSame('uploaded', $u->status);
        $this->assertSame(4, $u->size_bytes);
        Storage::disk('media')->assertExists($u->path);
        $this->assertSame('DATA', Storage::disk('media')->get($u->path));
    }

    public function test_invalid_signature_is_403(): void
    {
        $r = $this->target()->assertCreated();
        $this->sendRaw(str_replace('signature=', 'signature=x', $r->json('upload_url')), 'DATA')
            ->assertForbidden()->assertJsonStructure(['message']);
        $this->sendRaw('/uploads/'.$r->json('upload_id'), 'DATA')->assertForbidden();
        $this->assertSame('pending', MediaUpload::first()->status);
    }

    public function test_signed_put_works_behind_tls_terminating_proxy(): void
    {
        // Public address is https; the proxy forwards plain http with X-Forwarded-Proto.
        URL::forceRootUrl('https://portal.example.com');
        URL::forceScheme('https');

        $r = $this->target('video', 'video/mp4', 4)->assertCreated();
        $url = $r->json('upload_url');
        $this->assertStringStartsWith('https://portal.example.com/uploads/', $url);

        $asProxied = 'http://'.Str::after($url, 'https://');
        $this->sendRaw($asProxied, 'DATA')->assertForbidden();
        $this->sendRaw($asProxied, 'DATA', 'video/mp4', ['HTTP_X_FORWARDED_PROTO' => 'https'])->assertNoContent();
    }

    public function test_expired_signature_and_expired_record(): void
    {
        $r = $this->target()->assertCreated();
        $this->travel(2)->hours();
        $this->sendRaw($r->json('upload_url'), 'DATA')->assertForbidden();
        $this->travelBack();

        $r = $this->target()->assertCreated();
        MediaUpload::find($r->json('upload_id'))->update(['expires_at' => now()->subMinute()]);
        $this->sendRaw($r->json('upload_url'), 'DATA')->assertStatus(410)->assertJsonStructure(['message']);
    }

    public function test_unknown_upload_is_404(): void
    {
        $url = \URL::temporarySignedRoute('uploads.put', now()->addHour(), ['upload' => (string) Str::uuid()]);
        $this->sendRaw($url, 'DATA')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_mime_mismatch_is_415(): void
    {
        $r = $this->target('video', 'video/mp4')->assertCreated();
        $this->sendRaw($r->json('upload_url'), 'DATA', 'application/pdf')->assertStatus(415);
        $this->sendRaw($r->json('upload_url'), 'DATA', 'application/octet-stream')->assertStatus(415);
        $this->assertSame('pending', MediaUpload::first()->status);
    }

    public function test_declared_content_length_over_limit_is_413(): void
    {
        $r = $this->target()->assertCreated();
        MediaUpload::first()->update(['max_bytes' => 10]);
        $this->sendRaw($r->json('upload_url'), 'short', 'video/mp4', ['CONTENT_LENGTH' => 11])->assertStatus(413);
        $this->assertSame('pending', MediaUpload::first()->status);
    }

    public function test_streamed_body_over_limit_is_413_and_partial_file_deleted(): void
    {
        $r = $this->target()->assertCreated();
        MediaUpload::first()->update(['max_bytes' => 10]);

        $this->sendRaw($r->json('upload_url'), str_repeat('a', 5000))->assertStatus(413)->assertJsonStructure(['message']);

        $u = MediaUpload::first();
        $this->assertSame('pending', $u->status);
        Storage::disk('media')->assertMissing($u->path);
    }

    public function test_empty_body_rejected(): void
    {
        $r = $this->target()->assertCreated();
        $this->sendRaw($r->json('upload_url'), '')->assertUnprocessable();
        Storage::disk('media')->assertMissing(MediaUpload::first()->path);
    }

    public function test_second_put_is_409_and_keeps_first_file(): void
    {
        $r = $this->target('video', 'video/mp4', 4)->assertCreated();
        $this->sendRaw($r->json('upload_url'), 'DATA')->assertNoContent();
        $this->sendRaw($r->json('upload_url'), 'OTHER')->assertStatus(409);
        $this->assertSame('DATA', Storage::disk('media')->get(MediaUpload::first()->path));
    }

    // ---- CORS

    public function test_preflight_allows_only_frontend_origin(): void
    {
        $id = (string) Str::uuid();
        $pre = fn (string $origin) => $this->call('OPTIONS', "/uploads/$id", [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $ok = $pre('http://localhost:3000');
        $this->assertTrue($ok->isSuccessful());
        $ok->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->assertStringContainsString('PUT', $ok->headers->get('Access-Control-Allow-Methods'));

        $this->assertNotSame('http://evil.example', $pre('http://evil.example')->headers->get('Access-Control-Allow-Origin')); // single-origin config always answers with the allowed origin, so the browser rejects the mismatch
    }

    public function test_actual_put_carries_cors_header_and_api_has_none(): void
    {
        $r = $this->target('video', 'video/mp4', 4)->assertCreated();
        $this->sendRaw($r->json('upload_url'), 'DATA', 'video/mp4', ['HTTP_ORIGIN' => 'http://localhost:3000'])
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');

        $this->getJson('/api/educations', $this->h + ['Origin' => 'http://localhost:3000'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->call('OPTIONS', '/api/educations', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    // ---- content creation

    private function addContent(string $type, ?string $uploadId, ?Education $e = null, string $title = 'T')
    {
        return $this->postJson('/api/educations/'.($e ?? $this->edu)->id.'/contents', [
            'type' => $type, 'title' => $title, 'is_required' => true, 'upload_id' => $uploadId, 'duration_seconds' => 120.4,
        ], $this->h);
    }

    public function test_video_duration_is_required_and_bounded(): void
    {
        foreach ([null, 0, 0.5, -3, 86401, 'abc'] as $bad) {
            [$id] = $this->uploaded('video', 'video/mp4', 'DATA');
            $body = ['type' => 'video', 'title' => 'T', 'upload_id' => $id];
            if ($bad !== null) {
                $body['duration_seconds'] = $bad;
            }
            $this->postJson("/api/educations/{$this->edu->id}/contents", $body, $this->h)
                ->assertUnprocessable()->assertJsonValidationErrors(['duration_seconds']);
        }
        $this->assertSame(0, VideoContent::count());

        [$id] = $this->uploaded('video', 'video/mp4', 'DATA');
        $this->postJson("/api/educations/{$this->edu->id}/contents", [
            'type' => 'video', 'title' => 'T', 'upload_id' => $id, 'duration_seconds' => 86400,
        ], $this->h)->assertCreated();
    }

    public function test_create_video_content_from_upload(): void
    {
        [$id, $path] = $this->uploaded('video', 'video/mp4', 'VIDEODATA');

        $this->addContent('video', $id)->assertCreated()
            ->assertJsonPath('data.kind', 'video')
            ->assertJsonPath('data.properties', ['mime_type' => 'video/mp4', 'size_bytes' => 9, 'duration_seconds' => 121]);

        $v = VideoContent::first();
        $this->assertSame('local', $v->media_driver);
        $this->assertSame($path, $v->media_path);
        $this->assertSame(9, $v->size_bytes);
        $this->assertSame(121, $v->duration_seconds); // ceil
        Storage::disk('media')->assertExists($path);
    }

    public function test_create_document_content_from_upload(): void
    {
        [$id] = $this->uploaded('document', 'application/pdf', '%PDF-1.4');

        $this->addContent('document', $id)->assertCreated()
            ->assertJsonPath('data.kind', 'document')
            ->assertJsonPath('data.properties', ['mime_type' => 'application/pdf', 'size_bytes' => 8]);
        $this->assertSame(1, DocumentContent::count());
    }

    public function test_content_rejects_pending_foreign_wrong_kind_and_reused_uploads(): void
    {
        $pending = $this->target()->assertCreated()->json('upload_id');
        $this->addContent('video', $pending)->assertUnprocessable()->assertJsonValidationErrors(['upload_id']);

        $other = Education::create(['name' => 'Diğer', 'type' => 'technical']);
        [$foreign] = $this->uploaded('video', 'video/mp4', 'DATA', $other);
        $this->addContent('video', $foreign)->assertUnprocessable()->assertJsonValidationErrors(['upload_id']);

        [$pdf] = $this->uploaded('document', 'application/pdf', 'PDF');
        $this->addContent('video', $pdf)->assertUnprocessable()->assertJsonValidationErrors(['upload_id']);

        $this->addContent('video', (string) Str::uuid())->assertUnprocessable()->assertJsonValidationErrors(['upload_id']);
        $this->addContent('video', 'not-a-uuid')->assertUnprocessable()->assertJsonValidationErrors(['upload_id']);

        [$id] = $this->uploaded();
        $this->addContent('video', $id)->assertCreated();
        $this->addContent('video', $id)->assertUnprocessable()->assertJsonValidationErrors(['upload_id']);
        $this->assertSame(1, EducationContent::count());
    }

    public function test_content_rejects_upload_whose_file_is_missing(): void
    {
        [$id, $path] = $this->uploaded();
        Storage::disk('media')->delete($path);

        $this->addContent('video', $id)->assertUnprocessable()->assertJsonValidationErrors(['upload_id']);
    }

    public function test_deleting_content_deletes_file(): void
    {
        [$id, $path] = $this->uploaded();
        $contentId = $this->addContent('video', $id)->json('data.id');

        $this->deleteJson("/api/contents/$contentId", [], $this->h)->assertNoContent();

        Storage::disk('media')->assertMissing($path);
        $this->assertSame(0, VideoContent::count());
    }

    public function test_deleting_education_deletes_files_including_unattached_uploads(): void
    {
        [$id, $path] = $this->uploaded();
        $this->addContent('video', $id)->assertCreated();
        [, $orphan] = $this->uploaded('document', 'application/pdf', 'PDF');

        $this->deleteJson("/api/educations/{$this->edu->id}", [], $this->h)->assertNoContent();

        Storage::disk('media')->assertMissing($path);
        Storage::disk('media')->assertMissing($orphan);
    }

    // ---- streaming

    public function test_media_range_request_returns_206(): void
    {
        [$id, $path] = $this->uploaded('video', 'video/mp4', str_repeat('0123456789', 10));
        $url = app(MediaManager::class)->driver('local')->playbackUrl($path, now()->addHours(2));

        $this->get($url, ['Range' => 'bytes=10-19'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 10-19/100')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'video/mp4');

        $this->get($url)->assertOk()->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_pdf_is_served_inline_with_pdf_type(): void
    {
        [, $path] = $this->uploaded('document', 'application/pdf', '%PDF-1.4');
        $url = app(MediaManager::class)->driver('local')->playbackUrl($path, now()->addHour());

        $r = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', $r->headers->get('Content-Disposition'));
    }

    public function test_media_requires_valid_signature(): void
    {
        [, $path] = $this->uploaded();
        $url = app(MediaManager::class)->driver('local')->playbackUrl($path, now()->addHour());

        $this->get(strtok($url, '?'))->assertForbidden();
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->travel(3)->hours();
        $this->get($url)->assertForbidden();
    }

    public function test_media_path_traversal_rejected(): void
    {
        Storage::disk('media')->put('secret.mp4', 'nope');
        foreach (['../.env', '../../.env', '1/../../x.mp4', '%2e%2e/x', '1/abc.mp4', '../secret.mp4'] as $bad) {
            $url = \URL::temporarySignedRoute('media.show', now()->addHour(), ['path' => $bad]);
            $this->get($url)->assertNotFound();
        }
    }

    // ---- prune

    public function test_prune_removes_old_unattached_uploads_only(): void
    {
        [$attachedId, $attachedPath] = $this->uploaded();
        $this->addContent('video', $attachedId)->assertCreated();

        [$oldDone, $oldDonePath] = $this->uploaded('document', 'application/pdf', 'PDF');
        $oldPending = $this->target()->assertCreated()->json('upload_id');
        [$fresh, $freshPath] = $this->uploaded('document', 'application/pdf', 'PDF2');

        MediaUpload::whereIn('id', [$oldDone, $oldPending])->update(['created_at' => now()->subHours(25)]);
        MediaUpload::find($oldPending)->update(['expires_at' => now()->subHours(24)]);

        $this->artisan('media:prune')->assertSuccessful();

        $this->assertNull(MediaUpload::find($oldDone));
        $this->assertNull(MediaUpload::find($oldPending));
        Storage::disk('media')->assertMissing($oldDonePath);
        $this->assertNotNull(MediaUpload::find($fresh));
        Storage::disk('media')->assertExists($freshPath);
        Storage::disk('media')->assertExists($attachedPath);
        $this->assertSame(1, VideoContent::count());

        $this->artisan('media:prune')->assertSuccessful(); // idempotent
        Storage::disk('media')->assertExists($attachedPath);
    }

    // ---- public URL (requests may arrive on an internal host)

    public function test_signed_urls_use_app_url_even_when_request_hits_internal_host(): void
    {
        $r = $this->postJson("http://egitim-portal:8000/api/educations/{$this->edu->id}/uploads", [
            'kind' => 'video', 'filename' => 'a.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 4,
        ], $this->h)->assertCreated();

        $url = $r->json('upload_url');
        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/uploads/', $url);
        $this->assertStringNotContainsString('egitim-portal', $url);

        // valid on the public host ...
        $this->sendRaw($url, 'DATA')->assertNoContent();

        // ... and playback URL too
        $contentId = $this->postJson("http://egitim-portal:8000/api/educations/{$this->edu->id}/contents", [
            'type' => 'video', 'title' => 'V', 'upload_id' => $r->json('upload_id'), 'duration_seconds' => 30,
        ], $this->h)->assertCreated()->json('data.id');
        $this->assertNotNull($contentId);
    }

    public function test_playback_url_uses_app_url_and_signature_is_host_bound(): void
    {
        [$id, $path] = $this->uploaded();
        $this->addContent('video', $id)->assertCreated();
        $link = $this->postJson('http://egitim-portal:8000/api/assignments/'.$this->makeAssignment()->id.'/learning-link', [], $this->h)
            ->assertCreated()->json('token');

        $r = $this->getJson('http://egitim-portal:8000/api/learn', ['X-Learning-Token' => $link])->assertOk();
        $playback = $r->json('data.contents.0.playback_url');

        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/media/', $playback);
        $this->get($playback)->assertOk();

        // the same signature does not validate on the internal host
        $internal = str_replace(rtrim(config('app.url'), '/'), 'http://egitim-portal:8000', $playback);
        $this->get($internal)->assertForbidden();
    }

    private function makeAssignment(): EducationAssignment
    {
        return EducationAssignment::create([
            'education_id' => $this->edu->id, 'personnel_id' => 1, 'personnel_name' => 'A',
            'personnel_email' => 'a@b.c', 'status' => 'assigned', 'assigned_at' => now(),
        ]);
    }
}
