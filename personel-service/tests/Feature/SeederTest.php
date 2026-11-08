<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_is_idempotent_and_admin_password_follows_config(): void
    {
        config(['app.admin_email' => 'boss@example.com', 'app.admin_password' => 'first-pass', 'app.service_token' => 'seed-service-token']);

        $this->seed();
        $this->seed();

        $this->assertSame(4, Department::count());
        $this->assertSame(12, Personnel::count());
        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
        $this->assertSame(2, User::count());
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/auth/login', ['email' => 'boss@example.com', 'password' => 'first-pass'])->assertOk();

        config(['app.admin_password' => 'second-pass']);
        $this->seed();
        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
        $this->postJson('/api/auth/login', ['email' => 'boss@example.com', 'password' => 'second-pass'])->assertOk();
    }

    public function test_no_service_token_when_env_empty(): void
    {
        config(['app.service_token' => null]);
        $this->seed();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
