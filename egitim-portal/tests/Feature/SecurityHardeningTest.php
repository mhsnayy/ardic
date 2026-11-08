<?php

namespace Tests\Feature;

use App\Models\Education;
use App\Models\EducationAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, array $server = [], array $headers = [])
    {
        return $this->withServerVariables($server)
            ->postJson('/api/auth/login', ['email' => $email, 'password' => 'x'], $headers);
    }

    // --- 1. seeder owns the single admin ---

    public function test_changing_admin_email_removes_old_admin_and_tokens(): void
    {
        config(['portal.admin.email' => 'admin@example.com', 'portal.admin.password' => 'password']);
        $this->seed(DatabaseSeeder::class);
        $old = User::where('email', 'admin@example.com')->first();
        $old->createToken('t', ['education:manage']);
        $extra = User::factory()->create();
        $extra->createToken('t', ['education:manage']);

        config(['portal.admin.email' => 'new@example.com', 'portal.admin.password' => 'new-pass-1']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame('new@example.com', User::first()->email);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'new@example.com', 'password' => 'new-pass-1'])->assertOk();
    }

    public function test_seeder_keeps_tokens_when_email_unchanged(): void
    {
        config(['portal.admin.email' => 'a@example.com', 'portal.admin.password' => 'pw-12345']);
        $this->seed(DatabaseSeeder::class);
        User::first()->createToken('t', ['education:manage']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    // --- 2. login rate limit ---

    public function test_login_limit_is_per_email_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('a@x.com')->assertStatus(422);
        }
        $this->login('a@x.com')->assertStatus(429)
            ->assertJsonPath('message', 'Çok fazla giriş denemesi yaptınız. Lütfen bir dakika sonra tekrar deneyin.');
        $this->login('A@X.com')->assertStatus(429);
        $this->login('b@x.com')->assertStatus(422);
    }

    public function test_forwarded_header_is_ignored_for_rate_limit_key(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('a@x.com', [], ['X-Forwarded-For' => "9.9.9.$i"])->assertStatus(422);
        }
        $this->login('a@x.com', [], ['X-Forwarded-For' => '8.8.8.8'])->assertStatus(429);
    }

    // --- 3. unauthenticated without Accept header ---

    public function test_unauthenticated_without_accept_header_is_401_json(): void
    {
        $this->get('/api/educations')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    // --- 4. numeric route params ---

    public function test_non_numeric_ids_return_404(): void
    {
        $h = $this->adminHeaders();

        $this->getJson('/api/educations/abc', $h)->assertNotFound();
        $this->putJson('/api/educations/abc', ['name' => 'x', 'type' => 'technical'], $h)->assertNotFound();
        $this->deleteJson('/api/educations/abc', [], $h)->assertNotFound();
        $this->getJson('/api/educations/abc/assignments', $h)->assertNotFound();
        $this->patchJson('/api/assignments/abc', ['status' => 'completed'], $h)->assertNotFound();
        $this->deleteJson('/api/assignments/abc', [], $h)->assertNotFound();
        $this->getJson('/api/personnel/abc/assignments', $h)->assertNotFound();
    }

    public function test_non_numeric_department_id_is_422(): void
    {
        $this->fakePersonnel();

        $this->getJson('/api/personnel?department_id=abc', $this->adminHeaders())
            ->assertStatus(422)->assertJsonValidationErrors('department_id');
    }

    // --- 5. personnel service auth failures are logged ---

    public function test_personnel_401_is_logged_as_error_without_token(): void
    {
        config(['services.personnel.token' => 'super-secret-token']);
        $this->httpFake(['*/up' => Http::response('ok'), '*' => Http::response(['message' => 'no'], 401)]);
        $h = $this->adminHeaders();
        Log::spy();

        $this->getJson('/api/personnel', $h)->assertOk()->assertJsonPath('meta.personnel_source', 'unavailable');

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($msg, $ctx = []) => ! str_contains($msg.json_encode($ctx), 'super-secret-token') && ($ctx['status'] ?? null) === 401
        );
    }

    public function test_personnel_403_is_logged_as_error_and_429_as_warning(): void
    {
        $h = $this->adminHeaders();

        $this->httpFake(['*/up' => Http::response('ok'), '*' => Http::response([], 403)]);
        Log::spy();
        $this->getJson('/api/personnel', $h)->assertOk()->assertJsonPath('meta.personnel_source', 'unavailable');
        Log::shouldHaveReceived('error')->once();

        $this->httpFake(['*/up' => Http::response('ok'), '*' => Http::response([], 429)]);
        $this->getJson('/api/personnel', $h)->assertOk()->assertJsonPath('meta.personnel_source', 'unavailable');
        Log::shouldHaveReceived('warning')->once();
    }

    // --- 6. test gaps ---

    public function test_unique_violation_during_insert_falls_back_to_already_assigned(): void
    {
        $edu = Education::create(['name' => 'E', 'type' => 'technical']);
        $this->fakePersonnel();
        $h = $this->adminHeaders();
        $injected = false;

        // Simulate a concurrent request inserting the row after the service read existing rows.
        // The row is inserted right after the service's "existing" SELECT (outside the insert savepoint).
        DB::listen(function ($query) use (&$injected, $edu) {
            if (! $injected && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'education_assignments')) {
                $injected = true;
                DB::table('education_assignments')->insert([
                    'education_id' => $edu->id, 'personnel_id' => 1,
                    'personnel_name' => 'X', 'personnel_email' => 'x@example.com',
                    'status' => 'assigned', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $this->postJson("/api/educations/{$edu->id}/assignments", ['personnel_ids' => [1, 2]], $h)
            ->assertCreated()
            ->assertExactJson(['created' => [2], 'already_assigned' => [1], 'not_found' => []]);
        $this->assertSame(2, EducationAssignment::count());
    }

    public function test_expired_token_gets_401(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t', ['education:manage'], now()->subMinute())->plainTextToken;

        $this->getJson('/api/educations', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }
}
