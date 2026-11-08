<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Exceptions\PersonnelUnavailableException;
use App\Models\Education;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AssignmentService
{
    public function __construct(private readonly PersonnelApiClient $client) {}

    /**
     * Assign personnel to an education after live validation (BR-3).
     *
     * @param  array<int, int>  $personnelIds
     * @return array{created: int[], already_assigned: int[], not_found: int[]}
     *
     * @throws PersonnelUnavailableException
     */
    public function assign(Education $education, array $personnelIds): array
    {
        $live = $this->client->fetchAllLive();

        $existing = $education->assignments()
            ->whereIn('personnel_id', $personnelIds)
            ->pluck('personnel_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $created = $alreadyAssigned = $notFound = [];

        foreach (array_unique(array_map('intval', $personnelIds)) as $id) {
            if (! isset($live[$id])) {
                $notFound[] = $id;
            } elseif (in_array($id, $existing, true)) {
                $alreadyAssigned[] = $id;
            } else {
                $person = $live[$id];
                try {
                    // Savepoint: on Postgres a unique violation aborts the whole
                    // surrounding transaction unless it is rolled back to a savepoint.
                    DB::transaction(fn () => $education->assignments()->create([
                        'personnel_id' => $id,
                        'personnel_name' => $person['full_name'],
                        'personnel_email' => $person['email'],
                        'department_name' => $person['department']['name'] ?? null,
                        'status' => AssignmentStatus::Assigned,
                        'assigned_at' => now(),
                    ]));
                    $created[] = $id;
                } catch (UniqueConstraintViolationException) {
                    $alreadyAssigned[] = $id; // lost a race (BR-1)
                }
            }
        }

        return ['created' => $created, 'already_assigned' => $alreadyAssigned, 'not_found' => $notFound];
    }
}
