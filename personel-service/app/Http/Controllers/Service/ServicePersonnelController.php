<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonnelResource;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServicePersonnelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['department_id' => 'nullable|integer']);

        $personnel = Personnel::query()
            ->with('department')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->query('department_id')))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->get();

        return PersonnelResource::collection($personnel);
    }

    public function show(Personnel $personnel): PersonnelResource
    {
        return new PersonnelResource($personnel->load('department'));
    }
}
