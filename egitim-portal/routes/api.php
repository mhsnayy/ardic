<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EducationController;
use App\Http\Controllers\Api\PersonnelController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'abilities:education:manage'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('education-types', [EducationController::class, 'types']);
    Route::apiResource('educations', EducationController::class);
    Route::get('educations/{education}/assignments', [AssignmentController::class, 'index']);
    Route::post('educations/{education}/assignments', [AssignmentController::class, 'store']);
    Route::post('educations/{education}/uploads', [UploadController::class, 'store']);
    Route::get('educations/{education}/contents', [ContentController::class, 'index']);
    Route::post('educations/{education}/contents', [ContentController::class, 'store']);
    Route::patch('educations/{education}/contents/order', [ContentController::class, 'reorder']);
    Route::delete('contents/{content}', [ContentController::class, 'destroy']);
    Route::patch('assignments/{assignment}', [AssignmentController::class, 'update']);
    Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy']);

    Route::get('personnel', [PersonnelController::class, 'index']);
    Route::get('personnel/{id}/assignments', [PersonnelController::class, 'assignments'])->whereNumber('id');
    Route::get('departments', DepartmentController::class);
});
