<?php

namespace App\Media;

use App\Contracts\MediaSource;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Private 'media' disk (storage/app/media/...). Stored paths look like media/{education_id}/{uuid}.{ext};
 * the uuid in the file name doubles as the media_uploads id, which is how the signed PUT URL is built.
 */
class LocalMediaSource implements MediaSource
{
    private function disk(): Filesystem
    {
        return Storage::disk('media'); // resolved lazily so Storage::fake() works
    }

    public function createUploadTarget(string $path, string $mime, int $maxBytes, DateTimeInterface $exp): UploadTarget
    {
        $url = URL::temporarySignedRoute('uploads.put', $exp, ['upload' => pathinfo($path, PATHINFO_FILENAME)]);

        return new UploadTarget($url, 'PUT', ['Content-Type' => $mime]);
    }

    public function playbackUrl(string $path, DateTimeInterface $exp): string
    {
        return URL::temporarySignedRoute('media.show', $exp, ['path' => Str::after($path, 'media/')]);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }
}
