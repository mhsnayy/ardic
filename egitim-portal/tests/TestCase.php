<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** @return array<string, string> */
    protected function adminHeaders(array $abilities = ['education:manage']): array
    {
        $user = User::factory()->create();

        return ['Authorization' => 'Bearer '.$user->createToken('t', $abilities)->plainTextToken];
    }

    /** @return array<int, array<string, mixed>> */
    protected function people(): array
    {
        $mk = fn (int $id, string $first, string $last, int $dep, string $depName) => [
            'id' => $id, 'first_name' => $first, 'last_name' => $last, 'full_name' => "$first $last",
            'email' => strtolower($first).'@example.com', 'department' => ['id' => $dep, 'name' => $depName],
        ];

        return [
            $mk(1, 'Ayse', 'Yilmaz', 10, 'İK'),
            $mk(2, 'Mehmet', 'Kaya', 10, 'İK'),
            $mk(3, 'Zeynep', 'Demir', 20, 'Bilgi İşlem'),
        ];
    }

    protected function httpFake(mixed $stubs): void
    {
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    protected function fakePersonnel(?array $people = null): void
    {
        $people ??= $this->people();
        $this->httpFake([
            '*/up' => Http::response('ok'),
            '*/api/service/departments' => Http::response(['data' => [['id' => 10, 'name' => 'İK'], ['id' => 20, 'name' => 'Bilgi İşlem']]]),
            '*/api/service/personnel/*' => function ($request) use ($people) {
                $id = (int) basename($request->url());
                foreach ($people as $p) {
                    if ($p['id'] === $id) {
                        return Http::response(['data' => $p]);
                    }
                }

                return Http::response(['message' => 'not found'], 404);
            },
            '*/api/service/personnel*' => function ($request) use ($people) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);
                $rows = isset($q['department_id'])
                    ? array_values(array_filter($people, fn ($p) => $p['department']['id'] === (int) $q['department_id']))
                    : $people;

                return Http::response(['data' => $rows]);
            },
        ]);
    }
}
