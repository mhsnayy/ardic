<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PersonnelApiClient;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __invoke(PersonnelApiClient $client): JsonResponse
    {
        $result = $client->departments();

        return response()->json([
            'data' => $result->data,
            'meta' => ['personnel_source' => $result->source],
        ]);
    }
}
