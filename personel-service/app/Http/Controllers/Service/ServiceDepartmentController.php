<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceDepartmentResource;
use App\Models\Department;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceDepartmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ServiceDepartmentResource::collection(Department::orderBy('name')->get());
    }
}
