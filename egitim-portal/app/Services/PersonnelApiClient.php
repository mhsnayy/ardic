<?php

namespace App\Services;

use App\Exceptions\PersonnelUnavailableException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Single access point to the Personnel System service API.
 */
class PersonnelApiClient
{
    private const TTL_SECONDS = 300;

    private const HEALTH_KEY = 'personnel:health';

    /** Cached read (5 min) with "last good" fallback. */
    public function list(?int $departmentId = null): PersonnelResult
    {
        return $this->read(
            'list:'.($departmentId ?? 'all'),
            fn () => array_map(
                $this->normalize(...),
                $this->get('/api/service/personnel', $departmentId !== null ? ['department_id' => $departmentId] : []) ?? [],
            ),
            [],
        );
    }

    public function find(int $id): PersonnelResult
    {
        return $this->read(
            'one:'.$id,
            function () use ($id) {
                $row = $this->get('/api/service/personnel/'.$id);

                return $row === null ? null : $this->normalize($row);
            },
            null,
        );
    }

    public function departments(): PersonnelResult
    {
        return $this->read(
            'departments',
            fn () => $this->get('/api/service/departments') ?? [],
            [],
        );
    }

    /**
     * Live full personnel list, never cached (BR-3).
     *
     * @return array<int, array<string, mixed>> keyed by personnel id
     *
     * @throws PersonnelUnavailableException
     */
    public function fetchAllLive(): array
    {
        $rows = $this->get('/api/service/personnel');
        if ($rows === null) {
            throw new PersonnelUnavailableException;
        }

        $result = [];
        foreach ($rows as $row) {
            $person = $this->normalize($row);
            $result[(int) $person['id']] = $person;
        }

        return $result;
    }

    private function read(string $key, Closure $fetch, mixed $empty): PersonnelResult
    {
        if (! $this->isUp()) {
            return $this->fallback($key, $empty);
        }

        $fresh = Cache::get('personnel:fresh:'.$key);
        if ($fresh !== null) {
            return new PersonnelResult($fresh, PersonnelResult::LIVE);
        }

        try {
            $data = $fetch();
        } catch (PersonnelUnavailableException) {
            return $this->fallback($key, $empty);
        }

        if ($data !== null) {
            Cache::put('personnel:fresh:'.$key, $data, self::TTL_SECONDS);
            Cache::forever('personnel:last_good:'.$key, $data);
        }

        return new PersonnelResult($data, PersonnelResult::LIVE);
    }

    private function fallback(string $key, mixed $empty): PersonnelResult
    {
        $lastGood = Cache::get('personnel:last_good:'.$key);

        return $lastGood !== null
            ? new PersonnelResult($lastGood, PersonnelResult::STALE)
            : new PersonnelResult($empty, PersonnelResult::UNAVAILABLE);
    }

    /** Cheap reachability probe (GET /up), result cached for a short time. */
    private function isUp(): bool
    {
        $cached = Cache::get(self::HEALTH_KEY);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $timeout = (int) config('services.personnel.health_timeout', 2);
        try {
            $up = Http::timeout($timeout)->connectTimeout($timeout)
                ->get(rtrim((string) config('services.personnel.url'), '/').'/up')
                ->successful();
        } catch (ConnectionException) {
            $up = false;
        }
        $this->markHealth($up);

        return $up;
    }

    private function markHealth(bool $up): void
    {
        Cache::put(self::HEALTH_KEY, $up, (int) config('services.personnel.health_ttl', 15));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>|null null when the resource is 404
     *
     * @throws PersonnelUnavailableException
     */
    private function get(string $path, array $query = []): ?array
    {
        try {
            $response = Http::withToken((string) config('services.personnel.token'))
                ->acceptJson()
                ->timeout(5)
                ->get(rtrim((string) config('services.personnel.url'), '/').$path, $query);
        } catch (ConnectionException $e) {
            $this->markHealth(false);
            throw new PersonnelUnavailableException(previous: $e);
        }

        if ($response->serverError()) {
            $this->markHealth(false);
            throw new PersonnelUnavailableException;
        }
        $this->markHealth(true);

        if ($response->status() === 404) {
            return null;
        }
        if ($response->failed()) {
            throw new PersonnelUnavailableException;
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new PersonnelUnavailableException;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        if (! isset($row['id']) || ! array_key_exists('email', $row)) {
            return $row;
        }

        return [
            'id' => (int) $row['id'],
            'first_name' => $row['first_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'full_name' => $row['full_name'] ?? trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
            'email' => $row['email'],
            'department' => isset($row['department']['id'])
                ? ['id' => (int) $row['department']['id'], 'name' => $row['department']['name'] ?? null]
                : null,
        ];
    }
}
