<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\EducationAssignment;
use App\Services\PersonnelApiClient;
use App\Services\PersonnelResult;
use App\Support\Progress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    public function index(Request $request, PersonnelApiClient $client): JsonResponse
    {
        $request->validate(['department_id' => ['nullable', 'integer', 'min:1']]);
        $departmentId = $request->filled('department_id') ? (int) $request->query('department_id') : null;
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $result = $client->list($departmentId);
        $people = array_values(array_filter($result->data, function (array $p) use ($search) {
            if ($search === '') {
                return true;
            }
            foreach (['first_name', 'last_name', 'full_name', 'email'] as $field) {
                if (str_contains(mb_strtolower((string) ($p[$field] ?? '')), $search)) {
                    return true;
                }
            }

            return false;
        }));

        $stats = EducationAssignment::query()
            ->whereIn('personnel_id', array_column($people, 'id'))
            ->selectRaw('personnel_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as completed', [AssignmentStatus::Completed->value])
            ->groupBy('personnel_id')
            ->get()
            ->keyBy('personnel_id');

        $data = array_map(function (array $p) use ($stats) {
            $total = (int) ($stats[$p['id']]->total ?? 0);
            $completed = (int) ($stats[$p['id']]->completed ?? 0);

            return $p + ['progress' => [
                'total' => $total,
                'completed' => $completed,
                'completion_rate' => Progress::rate($total, $completed),
            ]];
        }, $people);

        return response()->json(['data' => $data, 'meta' => ['personnel_source' => $result->source]]);
    }

    public function assignments(int $id, PersonnelApiClient $client): JsonResponse
    {
        $assignments = EducationAssignment::with(['education', 'activeLearningLink'])
            ->where('personnel_id', $id)
            ->orderByDesc('assigned_at')->orderByDesc('id')
            ->get();

        $result = $client->find($id);

        if ($assignments->isEmpty() && $result->data === null && $result->source === PersonnelResult::LIVE) {
            abort(404, 'Personel bulunamadı.');
        }

        $total = $assignments->count();
        $count = fn (AssignmentStatus $s) => $assignments->where('status', $s)->count();
        $completed = $count(AssignmentStatus::Completed);

        return response()->json([
            'data' => AssignmentResource::collection($assignments)->resolve(),
            'meta' => [
                'personnel_source' => $result->source,
                'personnel' => $result->data,
                'progress' => [
                    'total' => $total,
                    'assigned' => $count(AssignmentStatus::Assigned),
                    'in_progress' => $count(AssignmentStatus::InProgress),
                    'completed' => $completed,
                    'completion_rate' => Progress::rate($total, $completed),
                ],
            ],
        ]);
    }
}
