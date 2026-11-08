<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function asService(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['personnel:read']);
    }

    public function test_personnel_list_is_unpaginated_with_department_filter(): void
    {
        $this->asService();
        $a = Department::factory()->create();
        Personnel::factory()->count(20)->create(['department_id' => $a->id]);
        Personnel::factory()->count(2)->create();

        $this->getJson('/api/service/personnel')->assertOk()
            ->assertJsonCount(22, 'data')->assertJsonMissingPath('meta')
            ->assertJsonStructure(['data' => [['id', 'first_name', 'last_name', 'full_name', 'email', 'department' => ['id', 'name']]]]);
        $this->getJson("/api/service/personnel?department_id={$a->id}")->assertJsonCount(20, 'data');
    }

    public function test_show_and_404(): void
    {
        $this->asService();
        $p = Personnel::factory()->create();

        $this->getJson("/api/service/personnel/{$p->id}")->assertOk()->assertJsonPath('data.id', $p->id);
        $this->getJson('/api/service/personnel/99999')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_departments(): void
    {
        $this->asService();
        Department::factory()->create(['name' => 'B']);
        Department::factory()->create(['name' => 'A']);

        $this->getJson('/api/service/departments')->assertOk()
            ->assertJsonPath('data.0.name', 'A')
            ->assertJsonMissingPath('data.0.personnel_count');
    }

    public function test_service_token_cannot_access_admin(): void
    {
        $this->asService();
        $this->getJson('/api/admin/personnel')->assertForbidden();
        $this->postJson('/api/admin/departments', ['name' => 'X'])->assertForbidden();
        $this->deleteJson('/api/admin/personnel/1')->assertForbidden();
    }

    public function test_requests_without_token_are_401(): void
    {
        $this->getJson('/api/service/personnel')->assertUnauthorized();
        $this->getJson('/api/service/departments')->assertUnauthorized();
        $this->postJson('/api/admin/departments', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_admin_token_without_read_ability_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['personnel:write']);
        $this->getJson('/api/service/personnel')->assertForbidden();
    }

    public function test_service_endpoints_are_throttled(): void
    {
        $this->asService();
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/service/departments')->assertOk();
        }
        $this->getJson('/api/service/departments')->assertStatus(429);
    }

    public function test_plain_token_created_by_command_authenticates(): void
    {
        Personnel::factory()->create();
        $this->artisan('service:token', ['--token' => 'my-plain-service-token-123']);

        $this->withToken('my-plain-service-token-123')->getJson('/api/service/personnel')->assertOk()->assertJsonCount(1, 'data');
        $this->app['auth']->forgetGuards();
        $this->withToken('my-plain-service-token-123')->getJson('/api/admin/personnel')->assertForbidden();
    }
}
