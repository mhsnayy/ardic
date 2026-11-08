<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PersonnelRequest;
use App\Http\Resources\PersonnelResource;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PersonnelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['department_id' => 'nullable|integer']);

        $search = trim((string) $request->query('search', ''));

        $query = Personnel::query()
            ->with('department')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->query('department_id')))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
                foreach (['first_name', 'last_name', 'email'] as $column) {
                    $q->orWhereRaw("lower({$column}) like ? escape '!'", [$pattern]);
                }
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');

        return PersonnelResource::collection($query->paginate(15)->withQueryString());
    }

    public function store(PersonnelRequest $request): PersonnelResource
    {
        $personnel = Personnel::create($request->validated());

        return new PersonnelResource($personnel->load('department'));
    }

    public function show(Personnel $personnel): PersonnelResource
    {
        return new PersonnelResource($personnel->load('department'));
    }

    public function update(PersonnelRequest $request, Personnel $personnel): PersonnelResource
    {
        $personnel->update($request->validated());

        return new PersonnelResource($personnel->load('department'));
    }

    public function destroy(Personnel $personnel): Response
    {
        $personnel->delete();

        return response()->noContent();
    }
}
