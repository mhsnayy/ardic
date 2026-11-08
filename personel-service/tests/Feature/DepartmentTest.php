<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['personnel:read', 'personnel:write']);
    }

    public function test_list_is_unpaginated_sorted_with_counts(): void
    {
        $b = Department::factory()->create(['name' => 'B']);
        Department::factory()->create(['name' => 'A']);
        Personnel::factory()->count(2)->create(['department_id' => $b->id]);

        $this->getJson('/api/admin/departments')->assertOk()
            ->assertJsonPath('data.0.name', 'A')
            ->assertJsonPath('data.0.personnel_count', 0)
            ->assertJsonPath('data.1.personnel_count', 2)
            ->assertJsonMissingPath('meta');
    }

    public function test_create_update_and_validation(): void
    {
        $this->postJson('/api/admin/departments', ['name' => 'Hukuk'])->assertCreated()->assertJsonPath('data.name', 'Hukuk');
        $this->postJson('/api/admin/departments', ['name' => 'Hukuk'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/admin/departments', [])->assertJsonPath('errors.name.0', 'ad alanı zorunludur.');
        $this->postJson('/api/admin/departments', ['name' => str_repeat('a', 101)])->assertJsonValidationErrors('name');

        $d = Department::where('name', 'Hukuk')->first();
        $this->putJson("/api/admin/departments/{$d->id}", ['name' => 'Hukuk'])->assertOk();
        $this->putJson("/api/admin/departments/{$d->id}", ['name' => 'Hukuk Isleri'])->assertOk()->assertJsonPath('data.name', 'Hukuk Isleri');
    }

    public function test_br2_department_with_personnel_cannot_be_deleted(): void
    {
        $p = Personnel::factory()->create();

        $this->deleteJson("/api/admin/departments/{$p->department_id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Bu departmanda personel bulunduğu için silinemez.']);
        $this->assertDatabaseHas('departments', ['id' => $p->department_id]);

        $p->delete();
        $this->deleteJson("/api/admin/departments/{$p->department_id}")->assertNoContent();
    }

    public function test_foreign_key_is_restrict_at_database_level(): void
    {
        $p = Personnel::factory()->create();

        $this->expectException(QueryException::class);
        Department::whereKey($p->department_id)->delete();
    }
}
