<?php

namespace App\Http\Controllers\Api;

use App\Enums\EducationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\EducationRequest;
use App\Http\Resources\EducationResource;
use App\Models\Education;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EducationController extends Controller
{
    public function index(Request $request)
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $educations = Education::withStats()
            ->when($search !== '', function ($q) use ($search) {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $q->whereRaw("lower(name) like ? escape '!'", ['%'.$escaped.'%']);
            })
            ->orderByDesc('id')
            ->paginate(15);

        return EducationResource::collection($educations);
    }

    public function store(EducationRequest $request): JsonResponse
    {
        $education = Education::create($request->validated());

        return (new EducationResource(Education::withStats()->findOrFail($education->id)))
            ->response()->setStatusCode(201);
    }

    public function show(int $education): EducationResource
    {
        return new EducationResource(Education::withStats()->findOrFail($education));
    }

    public function update(EducationRequest $request, Education $education): EducationResource
    {
        $education->update($request->validated());

        return new EducationResource(Education::withStats()->findOrFail($education->id));
    }

    public function destroy(Education $education): Response
    {
        $education->delete();

        return response()->noContent();
    }

    public function types(): JsonResponse
    {
        return response()->json(['data' => array_map(
            fn (EducationType $t) => ['value' => $t->value, 'label' => $t->label()],
            EducationType::cases(),
        )]);
    }
}
