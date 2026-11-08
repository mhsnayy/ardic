<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Models\Education;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonnelTest extends TestCase
{
    use RefreshDatabase;

    private array $h;

    protected function setUp(): void
    {
        parent::setUp();
        $this->h = $this->adminHeaders();
    }

    private function assignTo(int $pid, string $name, AssignmentStatus $s = AssignmentStatus::Assigned): void
    {
        Education::create(['name' => 'E'.uniqid(), 'type' => 'technical'])->assignments()->create([
            'personnel_id' => $pid, 'personnel_name' => $name, 'personnel_email' => 'snap@x.com',
            'department_name' => 'Eski Departman', 'status' => $s, 'assigned_at' => now(),
            'completed_at' => $s === AssignmentStatus::Completed ? now() : null,
        ]);
    }

    public function test_personnel_list_with_progress(): void
    {
        $this->fakePersonnel();
        $this->assignTo(1, 'Ayse Yilmaz', AssignmentStatus::Completed);
        $this->assignTo(1, 'Ayse Yilmaz');
        $this->assignTo(1, 'Ayse Yilmaz');

        $res = $this->getJson('/api/personnel', $this->h)->assertOk();
        $res->assertJsonPath('meta.personnel_source', 'live')->assertJsonCount(3, 'data');
        $rows = collect($res->json('data'));
        $this->assertSame(['total' => 3, 'completed' => 1, 'completion_rate' => 33], $rows->firstWhere('id', 1)['progress']);
        $this->assertSame(['total' => 0, 'completed' => 0, 'completion_rate' => 0], $rows->firstWhere('id', 2)['progress']);
        $res->assertJsonPath('data.0.department.name', 'İK');
    }

    public function test_search_is_case_insensitive_and_department_filter(): void
    {
        $this->fakePersonnel();

        $this->getJson('/api/personnel?search=YILMAZ', $this->h)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
        $this->getJson('/api/personnel?search=zeynep@EXAMPLE', $this->h)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 3);
        $this->getJson('/api/personnel?department_id=10', $this->h)->assertJsonCount(2, 'data');
        $this->getJson('/api/personnel?department_id=10&search=kaya', $this->h)->assertJsonCount(1, 'data');
    }

    public function test_departments_endpoint(): void
    {
        $this->fakePersonnel();

        $this->getJson('/api/departments', $this->h)->assertOk()
            ->assertJsonPath('data.0', ['id' => 10, 'name' => 'İK'])
            ->assertJsonPath('meta.personnel_source', 'live');
    }

    public function test_stale_when_system_goes_down_after_a_good_read(): void
    {
        $this->fakePersonnel();
        $this->getJson('/api/personnel', $this->h)->assertJsonPath('meta.personnel_source', 'live');
        $this->getJson('/api/departments', $this->h);

        Cache::forget('personnel:fresh:list:all');
        Cache::forget('personnel:fresh:departments');
        $this->httpFake(fn () => throw new ConnectionException('down'));

        $this->getJson('/api/personnel', $this->h)->assertOk()
            ->assertJsonPath('meta.personnel_source', 'stale')->assertJsonCount(3, 'data');
        $this->getJson('/api/departments', $this->h)->assertOk()->assertJsonPath('meta.personnel_source', 'stale');
    }

    public function test_stale_on_5xx(): void
    {
        $this->fakePersonnel();
        $this->getJson('/api/personnel', $this->h);
        Cache::forget('personnel:fresh:list:all');
        $this->httpFake(['*' => Http::response('err', 503)]);

        $this->getJson('/api/personnel', $this->h)->assertJsonPath('meta.personnel_source', 'stale');
    }

    public function test_unavailable_when_down_and_no_last_good(): void
    {
        $this->httpFake(fn () => throw new ConnectionException('down'));

        $this->getJson('/api/personnel', $this->h)->assertOk()
            ->assertJsonPath('meta.personnel_source', 'unavailable')->assertJsonPath('data', []);
        $this->getJson('/api/departments', $this->h)->assertOk()
            ->assertJsonPath('meta.personnel_source', 'unavailable')->assertJsonPath('data', []);
    }

    public function test_fresh_cache_avoids_second_call(): void
    {
        $this->fakePersonnel();
        $this->getJson('/api/personnel', $this->h);
        $this->getJson('/api/personnel', $this->h)->assertJsonPath('meta.personnel_source', 'live');

        Http::assertSentCount(1);
    }

    public function test_education_list_works_when_personnel_system_is_down(): void
    {
        $this->httpFake(fn () => throw new ConnectionException('down'));
        Education::create(['name' => 'E', 'type' => 'technical']);

        $this->getJson('/api/educations', $this->h)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_personnel_assignments_with_progress(): void
    {
        $this->fakePersonnel();
        $this->assignTo(1, 'Ayse Yilmaz', AssignmentStatus::Completed);
        $this->assignTo(1, 'Ayse Yilmaz', AssignmentStatus::InProgress);
        $this->assignTo(1, 'Ayse Yilmaz');
        $this->assignTo(1, 'Ayse Yilmaz');

        $this->getJson('/api/personnel/1/assignments', $this->h)->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.personnel_source', 'live')
            ->assertJsonPath('meta.personnel.full_name', 'Ayse Yilmaz')
            ->assertJsonPath('meta.progress', ['total' => 4, 'assigned' => 2, 'in_progress' => 1, 'completed' => 1, 'completion_rate' => 25])
            ->assertJsonStructure(['data' => [['id', 'status_label', 'education' => ['id', 'name', 'type', 'type_label']]]]);
    }

    public function test_snapshot_survives_personnel_deletion(): void
    {
        $this->fakePersonnel([]);
        $this->assignTo(7, 'Silinmiş Kişi');

        $this->getJson('/api/personnel/7/assignments', $this->h)->assertOk()
            ->assertJsonPath('meta.personnel', null)
            ->assertJsonPath('data.0.personnel_name', 'Silinmiş Kişi')
            ->assertJsonPath('data.0.personnel_email', 'snap@x.com')
            ->assertJsonPath('data.0.department_name', 'Eski Departman');
    }

    public function test_personnel_assignments_when_system_down(): void
    {
        $this->assignTo(7, 'Silinmiş Kişi');
        $this->httpFake(fn () => throw new ConnectionException('down'));

        $this->getJson('/api/personnel/7/assignments', $this->h)->assertOk()
            ->assertJsonPath('meta.personnel_source', 'unavailable')->assertJsonPath('meta.personnel', null)
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/personnel/8/assignments', $this->h)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unknown_personnel_without_assignments_is_404(): void
    {
        $this->fakePersonnel();

        $this->getJson('/api/personnel/999/assignments', $this->h)->assertNotFound();
        $this->getJson('/api/personnel/2/assignments', $this->h)->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.progress.total', 0);
    }
}
