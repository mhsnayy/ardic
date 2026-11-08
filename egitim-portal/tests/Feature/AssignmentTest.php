<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Models\Education;
use App\Models\EducationAssignment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private array $h;

    private Education $edu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->h = $this->adminHeaders();
        $this->edu = Education::create(['name' => 'Eğitim', 'type' => 'technical']);
        $this->fakePersonnel();
    }

    private function url(): string
    {
        return "/api/educations/{$this->edu->id}/assignments";
    }

    public function test_multiple_assignment_stores_snapshot(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [1, 2]], $this->h)
            ->assertCreated()
            ->assertExactJson(['created' => [1, 2], 'already_assigned' => [], 'not_found' => []]);

        $a = EducationAssignment::where('personnel_id', 1)->first();
        $this->assertSame('Ayse Yilmaz', $a->personnel_name);
        $this->assertSame('ayse@example.com', $a->personnel_email);
        $this->assertSame('İK', $a->department_name);
        $this->assertSame(AssignmentStatus::Assigned, $a->status);
        $this->assertNotNull($a->assigned_at);
        $this->assertNull($a->completed_at);
    }

    public function test_single_assignment(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [3]], $this->h)
            ->assertCreated()->assertJsonPath('created', [3]);
    }

    public function test_second_assignment_is_already_assigned_and_db_has_one_row(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h)->assertJsonPath('created', [1]);
        $this->postJson($this->url(), ['personnel_ids' => [1, 3]], $this->h)
            ->assertCreated()
            ->assertExactJson(['created' => [3], 'already_assigned' => [1], 'not_found' => []]);

        $this->assertSame(1, EducationAssignment::where('personnel_id', 1)->count());
    }

    public function test_unknown_personnel_is_not_found(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [1, 999]], $this->h)
            ->assertCreated()
            ->assertExactJson(['created' => [1], 'already_assigned' => [], 'not_found' => [999]]);
        $this->assertDatabaseCount('education_assignments', 1);
    }

    public function test_validation_of_personnel_ids(): void
    {
        $this->postJson($this->url(), [], $this->h)->assertStatus(422)->assertJsonValidationErrors('personnel_ids');
        $this->postJson($this->url(), ['personnel_ids' => []], $this->h)->assertStatus(422)->assertJsonValidationErrors('personnel_ids');
        $this->postJson($this->url(), ['personnel_ids' => [1, 1]], $this->h)->assertStatus(422);
        $this->postJson($this->url(), ['personnel_ids' => ['abc']], $this->h)->assertStatus(422);
    }

    public function test_database_enforces_unique_assignment(): void
    {
        $row = [
            'education_id' => $this->edu->id, 'personnel_id' => 1, 'personnel_name' => 'x', 'personnel_email' => 'x',
            'status' => 'assigned', 'assigned_at' => now(),
        ];
        EducationAssignment::create($row);

        $this->expectException(UniqueConstraintViolationException::class);
        EducationAssignment::create($row);
    }

    public function test_connection_error_gives_503_and_creates_nothing(): void
    {
        $this->httpFake(fn () => throw new ConnectionException('down'));

        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h)
            ->assertStatus(503)
            ->assertJsonPath('message', "Personel Sistemi'ne şu anda ulaşılamıyor. Atama yapılamadı.");
        $this->assertDatabaseCount('education_assignments', 0);
    }

    public function test_personnel_system_500_gives_503(): void
    {
        $this->httpFake(['*' => Http::response('boom', 500)]);

        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h)->assertStatus(503);
        $this->assertDatabaseCount('education_assignments', 0);
    }

    public function test_assignment_never_uses_cache(): void
    {
        $this->getJson('/api/personnel', $this->h)->assertJsonPath('meta.personnel_source', 'live');
        $this->assertNotNull(Cache::get('personnel:fresh:list:all'));

        // Person 1 vanished from the live system although the cache still has them.
        $this->fakePersonnel([$this->people()[2]]);
        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h)
            ->assertCreated()->assertJsonPath('not_found', [1]);

        // System down with a warm cache: assignment must still fail.
        $this->httpFake(fn () => throw new ConnectionException('down'));
        $this->postJson($this->url(), ['personnel_ids' => [2]], $this->h)->assertStatus(503);
        $this->assertDatabaseCount('education_assignments', 0);
    }

    public function test_service_request_carries_bearer_token(): void
    {
        config(['services.personnel.token' => 'svc-token']);
        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h)->assertCreated();

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer svc-token') && $r->hasHeader('Accept', 'application/json'));
    }

    public function test_list_assignments_sorted_by_name_without_pagination(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [3, 2, 1]], $this->h)->assertCreated();

        $res = $this->getJson($this->url(), $this->h)->assertOk();
        $this->assertSame(['Ayse Yilmaz', 'Mehmet Kaya', 'Zeynep Demir'], array_column($res->json('data'), 'personnel_name'));
        $res->assertJsonStructure(['data' => [['id', 'education_id', 'personnel_id', 'personnel_name', 'personnel_email', 'department_name', 'status', 'status_label', 'assigned_at', 'completed_at']]]);
        $this->assertArrayNotHasKey('meta', $res->json());
    }

    public function test_status_change_sets_and_clears_completed_at(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h);
        $a = EducationAssignment::first();

        $this->patchJson("/api/assignments/{$a->id}", ['status' => 'completed'], $this->h)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.status_label', 'Tamamlandı');
        $this->assertNotNull($a->fresh()->completed_at);

        $this->patchJson("/api/assignments/{$a->id}", ['status' => 'in_progress'], $this->h)
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.status_label', 'Devam Ediyor');
        $this->assertNull($a->fresh()->completed_at);
    }

    public function test_status_validation_and_missing_assignment(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h);
        $a = EducationAssignment::first();

        $this->patchJson("/api/assignments/{$a->id}", [], $this->h)->assertStatus(422)->assertJsonValidationErrors('status');
        $this->patchJson("/api/assignments/{$a->id}", ['status' => 'bogus'], $this->h)->assertStatus(422);
        $this->patchJson('/api/assignments/9999', ['status' => 'completed'], $this->h)->assertNotFound();
    }

    public function test_delete_assignment_and_reassign(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h);
        $a = EducationAssignment::first();

        $this->deleteJson("/api/assignments/{$a->id}", [], $this->h)->assertNoContent();
        $this->assertDatabaseCount('education_assignments', 0);

        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h)->assertJsonPath('created', [1]);
    }

    public function test_change_status_model_method(): void
    {
        $this->postJson($this->url(), ['personnel_ids' => [1]], $this->h);
        $a = EducationAssignment::first();

        $a->changeStatus(AssignmentStatus::Completed);
        $first = $a->completed_at;
        $a->changeStatus(AssignmentStatus::Completed);
        $this->assertTrue($first->equalTo($a->completed_at));
        $a->changeStatus(AssignmentStatus::Assigned);
        $this->assertNull($a->fresh()->completed_at);
    }
}
