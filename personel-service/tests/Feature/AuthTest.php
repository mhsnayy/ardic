<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@example.com', 'password' => 'secret123']);
    }

    public function test_login_succeeds_and_returns_token_with_abilities(): void
    {
        $this->admin();

        $response = $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email']]);

        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertSame(['personnel:read', 'personnel:write'], $token->abilities);
        $this->assertEqualsWithDelta(now()->addHours(8)->timestamp, $token->expires_at->timestamp, 5);
        $this->assertSame($token->expires_at->timestamp, Carbon::parse($response->json('expires_at'))->timestamp);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'E-posta veya şifre hatalı.')
            ->assertJsonStructure(['message', 'errors' => ['email']]);

        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'E-posta veya şifre hatalı.');
    }

    public function test_login_is_rate_limited_to_five_per_minute(): void
    {
        $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'secret123'])
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_service_user_cannot_login(): void
    {
        $this->artisan('service:token', ['--token' => 'abc']);
        $user = User::where('email', 'egitim-portal@service.local')->first();
        $user->forceFill(['password' => 'known-pass'])->save();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'known-pass'])
            ->assertStatus(422);
    }

    public function test_me_returns_user_and_logout_revokes_token(): void
    {
        $this->admin();
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'secret123'])->json('token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('user.email', 'admin@example.com');

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_unauthenticated_request_returns_json_401(): void
    {
        $this->getJson('/api/admin/personnel')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->get('/api/admin/personnel')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_expired_token_returns_401(): void
    {
        $token = $this->admin()->createToken('admin', ['personnel:write'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/personnel')->assertUnauthorized();
    }

    public function test_missing_ability_returns_403(): void
    {
        $token = $this->admin()->createToken('x', ['personnel:read'], now()->addHour())->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/personnel')
            ->assertForbidden()->assertJsonStructure(['message']);
    }
}
