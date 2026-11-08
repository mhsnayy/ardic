<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_with_expiry_and_ability(): void
    {
        User::factory()->create(['email' => 'a@x.com', 'password' => 'secret123']);

        $res = $this->postJson('/api/auth/login', ['email' => 'a@x.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email']]);

        $token = User::first()->tokens()->first();
        $this->assertSame(['education:manage'], $token->abilities);
        $this->assertTrue($token->expires_at->between(now()->addHours(7), now()->addHours(9)));
        $this->assertNotNull($res->json('expires_at'));
    }

    public function test_wrong_credentials_return_422(): void
    {
        User::factory()->create(['email' => 'a@x.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', ['email' => 'a@x.com', 'password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'E-posta veya şifre hatalı.');
    }

    public function test_login_is_rate_limited_to_five_per_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'a@x.com', 'password' => 'x'])->assertStatus(422);
        }
        $this->postJson('/api/auth/login', ['email' => 'a@x.com', 'password' => 'x'])
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_me_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])->json('token');
        $h = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/auth/me', $h)->assertOk()->assertJsonPath('user.email', $user->email);
        $this->postJson('/api/auth/logout', [], $h)->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_requests_without_token_get_401_json(): void
    {
        $this->getJson('/api/educations')->assertStatus(401)->assertJsonStructure(['message']);
        $this->getJson('/api/personnel')->assertStatus(401);
        $this->getJson('/api/departments')->assertStatus(401);
    }

    public function test_token_with_wrong_ability_gets_403(): void
    {
        $h = $this->adminHeaders(['personnel:read']);

        $this->getJson('/api/educations', $h)->assertStatus(403)->assertJsonStructure(['message']);
        $this->postJson('/api/educations', ['name' => 'x', 'type' => 'technical'], $h)->assertStatus(403);
    }

    public function test_seeder_is_idempotent_and_uses_config(): void
    {
        config(['portal.admin.email' => 'boss@example.com', 'portal.admin.password' => 'pw-12345']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
        $this->postJson('/api/auth/login', ['email' => 'boss@example.com', 'password' => 'pw-12345'])->assertOk();
    }
}
