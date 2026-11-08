<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUploadRequest;
use App\Media\MediaManager;
use App\Models\Education;
use App\Models\MediaUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(StoreUploadRequest $request, Education $education, MediaManager $media): JsonResponse
    {
        $kind = $request->validated('kind');
        $mime = $request->validated('mime_type');
        $maxBytes = (int) config("media.kinds.$kind.max_bytes");
        $expires = now()->addSeconds((int) config('media.upload_ttl'));

        // Never derive the path from the client's file name. The uuid doubles as the upload id.
        $id = (string) Str::uuid();
        $ext = config("media.kinds.$kind.mimes.$mime");
        $path = "media/{$education->id}/{$id}.{$ext}";

        $driverName = $media->getDefaultDriver();
        $target = $media->driver($driverName)->createUploadTarget($path, $mime, $maxBytes, $expires);

        MediaUpload::create([
            'id' => $id,
            'education_id' => $education->id,
            'kind' => $kind,
            'media_driver' => $driverName,
            'path' => $path,
            'mime_type' => $mime,
            'max_bytes' => $maxBytes,
            'status' => 'pending',
            'expires_at' => $expires,
        ]);

        return response()->json([
            'upload_id' => $id,
            'upload_url' => $target->url,
            'method' => $target->method,
            'headers' => $target->headers,
            'expires_at' => $expires->toIso8601String(),
            'max_bytes' => $maxBytes,
        ], 201);
    }
}
