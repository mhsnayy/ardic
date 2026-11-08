<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ServiceTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_hashed_token_with_read_ability_and_is_idempotent(): void
    {
        $this->artisan('service:token', ['--token' => 'tok-1'])->assertSuccessful();
        $this->artisan('service:token', ['--token' => 'tok-1'])->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('users', 1);
        $t = PersonalAccessToken::first();
        $this->assertSame(hash('sha256', 'tok-1'), $t->token);
        $this->assertSame(['personnel:read'], $t->abilities);
        $this->assertNull($t->expires_at);
        $this->assertSame('egitim-portal', $t->name);
        $this->assertSame('egitim-portal@service.local', User::first()->email);
    }

    public function test_changed_token_replaces_old_one(): void
    {
        $this->artisan('service:token', ['--token' => 'old']);
        $this->artisan('service:token', ['--token' => 'new']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken('old')->getJson('/api/service/departments')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken('new')->getJson('/api/service/departments')->assertOk();
    }

    public function test_generates_random_token_when_not_given(): void
    {
        $this->artisan('service:token')->assertSuccessful();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
