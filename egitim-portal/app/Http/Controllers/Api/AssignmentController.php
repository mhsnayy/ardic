<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Exceptions\PersonnelUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentsRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Education;
use App\Models\EducationAssignment;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AssignmentController extends Controller
{
    public function index(Education $education)
    {
        return AssignmentResource::collection(
            $education->assignments()->orderBy('personnel_name')->orderBy('id')->get()
        );
    }

    public function store(StoreAssignmentsRequest $request, Education $education, AssignmentService $service): JsonResponse
    {
        try {
            $result = $service->assign($education, $request->validated('personnel_ids'));
        } catch (PersonnelUnavailableException) {
            return response()->json([
                'message' => "Personel Sistemi'ne şu anda ulaşılamıyor. Atama yapılamadı.",
            ], 503);
        }

        return response()->json($result, 201);
    }

    public function update(UpdateAssignmentRequest $request, EducationAssignment $assignment): AssignmentResource
    {
        $assignment->changeStatus($request->enum('status', AssignmentStatus::class));

        return new AssignmentResource($assignment);
    }

    public function destroy(EducationAssignment $assignment): Response
    {
        $assignment->delete();

        return response()->noContent();
    }
}
