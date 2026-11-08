<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Models\Education;
use App\Models\EducationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EducationTest extends TestCase
{
    use RefreshDatabase;

    private array $h;

    protected function setUp(): void
    {
        parent::setUp();
        $this->h = $this->adminHeaders();
    }

    private function assign(Education $e, int $pid, AssignmentStatus $s = AssignmentStatus::Assigned): EducationAssignment
    {
        return EducationAssignment::create([
            'education_id' => $e->id, 'personnel_id' => $pid, 'personnel_name' => "P$pid",
            'personnel_email' => "p$pid@x.com", 'department_name' => 'D', 'status' => $s,
            'assigned_at' => now(), 'completed_at' => $s === AssignmentStatus::Completed ? now() : null,
        ]);
    }

    public function test_create_education(): void
    {
        $this->postJson('/api/educations', ['name' => 'Yangın', 'type' => 'safety', 'description' => 'Açıklama'], $this->h)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Yangın')
            ->assertJsonPath('data.type_label', 'İş Sağlığı ve Güvenliği')
            ->assertJsonPath('data.stats.total', 0)
            ->assertJsonPath('data.stats.completion_rate', 0);
    }

    public function test_validation_messages_are_turkish(): void
    {
        $this->postJson('/api/educations', [], $this->h)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type'])
            ->assertJsonPath('errors.name.0', 'ad alanı zorunludur.');

        $this->postJson('/api/educations', ['name' => str_repeat('a', 151), 'type' => 'bogus', 'description' => str_repeat('a', 2001)], $this->h)
            ->assertJsonValidationErrors(['name', 'type', 'description']);
    }

    public function test_show_update_delete(): void
    {
        $e = Education::create(['name' => 'A', 'type' => 'technical']);

        $this->getJson("/api/educations/{$e->id}", $this->h)->assertOk()->assertJsonPath('data.id', $e->id);
        $this->putJson("/api/educations/{$e->id}", ['name' => 'B', 'type' => 'compliance', 'description' => null], $this->h)
            ->assertOk()->assertJsonPath('data.name', 'B')->assertJsonPath('data.type', 'compliance');

        $this->assign($e, 1);
        $this->deleteJson("/api/educations/{$e->id}", [], $this->h)->assertNoContent();
        $this->assertDatabaseCount('educations', 0);
        $this->assertDatabaseCount('education_assignments', 0);
        $this->getJson("/api/educations/{$e->id}", $this->h)->assertNotFound();
    }

    public function test_index_search_and_stats(): void
    {
        $a = Education::create(['name' => 'Yangın Eğitimi', 'type' => 'safety']);
        Education::create(['name' => 'Excel', 'type' => 'technical']);
        $this->assign($a, 1, AssignmentStatus::Completed);
        $this->assign($a, 2, AssignmentStatus::InProgress);
        $this->assign($a, 3);
        $this->assign($a, 4, AssignmentStatus::Completed);
        $this->assign($a, 5);
        $this->assign($a, 6);

        $this->getJson('/api/educations?search=yangın', $this->h)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stats', ['total' => 6, 'assigned' => 3, 'in_progress' => 1, 'completed' => 2, 'completion_rate' => 33])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'total']]);

        $this->assertCount(2, $this->getJson('/api/educations', $this->h)->json('data'));
    }

    public function test_index_paginates_by_15(): void
    {
        for ($i = 0; $i < 16; $i++) {
            Education::create(['name' => "E$i", 'type' => 'technical']);
        }
        $this->getJson('/api/educations', $this->h)->assertJsonCount(15, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/educations?page=2', $this->h)->assertJsonCount(1, 'data');
    }

    public function test_index_has_no_n_plus_one(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assign(Education::create(['name' => "E$i", 'type' => 'technical']), $i + 1);
        }
        DB::enableQueryLog();
        $this->getJson('/api/educations', $this->h)->assertOk();
        $selects = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"educations"'))->count();
        $this->assertLessThanOrEqual(2, $selects);
    }

    public function test_education_types_endpoint(): void
    {
        $this->getJson('/api/education-types', $this->h)
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonFragment(['value' => 'soft_skills', 'label' => 'Kişisel Gelişim']);
    }
}
