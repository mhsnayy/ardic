<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_admin_email_removes_old_admin_and_tokens(): void
    {
        config(['app.admin_email' => 'admin@example.com', 'app.admin_password' => 'password', 'app.service_token' => 'svc-token']);
        $this->seed();
        $old = User::where('email', 'admin@example.com')->first();
        $old->createToken('t', ['personnel:write']);
        $extra = User::factory()->create(['email' => 'other@example.com']);
        $extra->createToken('t');

        config(['app.admin_email' => 'new@example.com', 'app.admin_password' => 'new-pass']);
        $this->seed();

        $this->assertSame(1, User::where('email', 'not like', '%@service.local')->count());
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(1, User::where('email', 'like', '%@service.local')->count());

        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'new@example.com', 'password' => 'new-pass'])->assertOk();
    }

    public function test_demo_data_loads_once_only(): void
    {
        $this->seed();
        $this->assertSame(4, Department::count());
        $this->assertSame(12, Personnel::count());

        Personnel::where('email', 'ahmet.yilmaz@example.com')->delete();
        Department::where('name', 'Satış')->first()->update(['name' => 'Pazarlama']);
        $this->seed();

        $this->assertSame(11, Personnel::count());
        $this->assertSame(4, Department::count());
        $this->assertDatabaseMissing('departments', ['name' => 'Satış']);
    }

    public function test_service_token_cannot_logout_or_me(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['personnel:read']);
        $this->postJson('/api/auth/logout')->assertForbidden();
        $this->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_non_numeric_ids_and_filters(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['personnel:read', 'personnel:write']);

        $this->getJson('/api/admin/personnel/abc')->assertNotFound();
        $this->putJson('/api/admin/personnel/abc', [])->assertNotFound();
        $this->deleteJson('/api/admin/departments/abc')->assertNotFound();
        $this->getJson('/api/service/personnel/abc')->assertNotFound();
        $this->getJson('/api/admin/personnel?department_id=abc')->assertStatus(422);
        $this->getJson('/api/service/personnel?department_id=abc')->assertStatus(422);
    }

    public function test_department_delete_fk_violation_returns_409(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['personnel:write']);
        $department = Department::create(['name' => 'X']);

        // check-then-delete yarisi: personel, exists() kontrolunden sonra eklenmis gibi.
        Department::deleting(function () {
            throw new QueryException('sqlite', 'delete from departments', [], new \Exception('FOREIGN KEY constraint failed'));
        });

        $this->deleteJson("/api/admin/departments/{$department->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Bu departmanda personel bulunduğu için silinemez.');
    }

    public function test_search_escapes_like_wildcards(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['personnel:write']);
        $d = Department::create(['name' => 'D']);
        Personnel::create(['first_name' => 'Under_score', 'last_name' => 'A', 'email' => 'a@example.com', 'department_id' => $d->id]);
        Personnel::create(['first_name' => 'Plain', 'last_name' => 'B', 'email' => 'b@example.com', 'department_id' => $d->id]);
        Personnel::create(['first_name' => '100%', 'last_name' => 'C', 'email' => 'c@example.com', 'department_id' => $d->id]);

        $this->getJson('/api/admin/personnel?search=_')->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Under_score');
        $this->getJson('/api/admin/personnel?search=%25')->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', '100%');
        $this->getJson('/api/admin/personnel?search=plain')->assertJsonCount(1, 'data');
    }
}
