<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\UploadReceiveController;
use Illuminate\Support\Facades\Route;

// Browser-facing, signed-URL endpoints (no session/CSRF, no /api prefix).
Route::put('uploads/{upload}', UploadReceiveController::class)
    ->middleware('signed')->name('uploads.put')->where('upload', '[0-9a-f\-]{36}');

Route::get('media/{path}', [MediaController::class, 'show'])
    ->middleware('signed')->name('media.show')->where('path', '.+');
