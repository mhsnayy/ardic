<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DepartmentResource::collection(Department::withCount('personnel')->orderBy('name')->get());
    }

    public function store(DepartmentRequest $request): DepartmentResource
    {
        return new DepartmentResource(Department::create($request->validated()));
    }

    public function update(DepartmentRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        return new DepartmentResource($department);
    }

    public function destroy(Department $department): Response|JsonResponse
    {
        $conflict = response()->json(['message' => 'Bu departmanda personel bulunduğu için silinemez.'], 409);

        if ($department->personnel()->exists()) {
            return $conflict;
        }

        try {
            $department->delete();
        } catch (QueryException) {
            return $conflict;
        }

        return response()->noContent();
    }
}
