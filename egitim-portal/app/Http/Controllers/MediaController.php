<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Signed, Range-capable stream of locally stored media (video seeking needs 206). */
class MediaController extends Controller
{
    private const TYPES = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf'];

    public function show(string $path): BinaryFileResponse
    {
        // Only files we generated: {education_id}/{uuid}.{ext}. Rejects '..', absolute paths, anything else.
        if (! preg_match('#^\d+/[0-9a-f\-]{36}\.(mp4|webm|pdf)$#', $path, $m)) {
            abort(404);
        }

        $disk = Storage::disk('media');
        if (! $disk->exists("media/$path")) {
            abort(404);
        }

        return response()->file($disk->path("media/$path"), [
            'Content-Type' => self::TYPES[$m[1]],
            'Cache-Control' => 'private, max-age=0',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
