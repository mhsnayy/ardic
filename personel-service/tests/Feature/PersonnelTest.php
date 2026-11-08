<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['personnel:read', 'personnel:write']);
    }

    public function test_create_returns_personnel_format(): void
    {
        $dept = Department::factory()->create(['name' => 'Finans']);

        $this->postJson('/api/admin/personnel', [
            'first_name' => 'Ali', 'last_name' => 'Veli', 'email' => 'ali@example.com', 'department_id' => $dept->id,
        ])->assertCreated()->assertJson(['data' => [
            'first_name' => 'Ali', 'last_name' => 'Veli', 'full_name' => 'Ali Veli', 'email' => 'ali@example.com',
            'department' => ['id' => $dept->id, 'name' => 'Finans'],
        ]]);
    }

    public function test_validation_errors_are_turkish(): void
    {
        $existing = Personnel::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/admin/personnel', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.first_name.0', 'ad alanı zorunludur.')
            ->assertJsonPath('errors.last_name.0', 'soyad alanı zorunludur.')
            ->assertJsonPath('errors.email.0', 'e-posta alanı zorunludur.')
            ->assertJsonPath('errors.department_id.0', 'departman alanı zorunludur.');

        $this->postJson('/api/admin/personnel', [
            'first_name' => str_repeat('a', 101), 'last_name' => 'x', 'email' => 'bad', 'department_id' => 9999,
        ])->assertJsonValidationErrors(['first_name', 'email', 'department_id']);

        $this->postJson('/api/admin/personnel', [
            'first_name' => 'a', 'last_name' => 'b', 'email' => 'dup@example.com', 'department_id' => $existing->department_id,
        ])->assertJsonValidationErrors(['email']);
    }

    public function test_show_update_delete(): void
    {
        $p = Personnel::factory()->create();
        $other = Department::factory()->create();

        $this->getJson("/api/admin/personnel/{$p->id}")->assertOk()->assertJsonPath('data.id', $p->id);

        $this->putJson("/api/admin/personnel/{$p->id}", [
            'first_name' => 'Yeni', 'last_name' => 'Ad', 'email' => $p->email, 'department_id' => $other->id,
        ])->assertOk()->assertJsonPath('data.department.id', $other->id)->assertJsonPath('data.first_name', 'Yeni');

        $this->deleteJson("/api/admin/personnel/{$p->id}")->assertNoContent();
        $this->assertDatabaseMissing('personnel', ['id' => $p->id]);
        $this->getJson("/api/admin/personnel/{$p->id}")->assertNotFound();
    }

    public function test_list_search_filter_and_pagination(): void
    {
        $it = Department::factory()->create();
        $hr = Department::factory()->create();
        Personnel::factory()->create(['first_name' => 'Zeynep', 'last_name' => 'Arslan', 'email' => 'z@example.com', 'department_id' => $it->id]);
        Personnel::factory()->create(['first_name' => 'Ahmet', 'last_name' => 'Arslan', 'email' => 'a@example.com', 'department_id' => $hr->id]);
        Personnel::factory()->create(['first_name' => 'Can', 'last_name' => 'Boz', 'email' => 'can@firma.com', 'department_id' => $hr->id]);

        $names = fn ($r) => collect($r->json('data'))->pluck('first_name')->all();

        $this->assertSame(['Ahmet', 'Zeynep', 'Can'], $names($this->getJson('/api/admin/personnel')));
        $this->assertSame(['Ahmet', 'Zeynep'], $names($this->getJson('/api/admin/personnel?search=ARSLAN')));
        $this->assertSame(['Can'], $names($this->getJson('/api/admin/personnel?search=FIRMA')));
        $this->assertSame(['Zeynep'], $names($this->getJson('/api/admin/personnel?search=zeyn')));
        $this->assertSame(['Ahmet', 'Can'], $names($this->getJson("/api/admin/personnel?department_id={$hr->id}")));
        $this->assertSame(['Ahmet'], $names($this->getJson("/api/admin/personnel?department_id={$hr->id}&search=arslan")));

        Personnel::factory()->count(20)->create();
        $this->getJson('/api/admin/personnel')
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 23);
        $this->getJson('/api/admin/personnel?page=2')->assertJsonCount(8, 'data');
    }
}
