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
        $fresh = Cache::get('personnel:fresh:'.$key);
        if ($fresh !== null) {
            return new PersonnelResult($fresh, PersonnelResult::LIVE);
        }

        try {
            $data = $fetch();
        } catch (PersonnelUnavailableException) {
            $lastGood = Cache::get('personnel:last_good:'.$key);

            return $lastGood !== null
                ? new PersonnelResult($lastGood, PersonnelResult::STALE)
                : new PersonnelResult($empty, PersonnelResult::UNAVAILABLE);
        }

        if ($data !== null) {
            Cache::put('personnel:fresh:'.$key, $data, self::TTL_SECONDS);
            Cache::forever('personnel:last_good:'.$key, $data);
        }

        return new PersonnelResult($data, PersonnelResult::LIVE);
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
            throw new PersonnelUnavailableException(previous: $e);
        }

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
