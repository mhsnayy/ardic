<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Service\ServiceDepartmentController;
use App\Http\Controllers\Service\ServicePersonnelController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::prefix('admin')->middleware(['auth:sanctum', 'abilities:personnel:write'])->group(function () {
    Route::apiResource('personnel', PersonnelController::class)->parameters(['personnel' => 'personnel']);
    Route::apiResource('departments', DepartmentController::class)->except('show');
});

Route::prefix('service')->middleware(['auth:sanctum', 'abilities:personnel:read', 'throttle:60,1'])->group(function () {
    Route::get('personnel', [ServicePersonnelController::class, 'index']);
    Route::get('personnel/{personnel}', [ServicePersonnelController::class, 'show']);
    Route::get('departments', [ServiceDepartmentController::class, 'index']);
});
