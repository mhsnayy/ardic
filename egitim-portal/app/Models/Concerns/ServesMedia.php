<?php

namespace App\Models\Concerns;

use App\Media\MediaManager;
use DateTimeInterface;

/** Playback URL for file-backed contentables, via the record's own media driver. */
trait ServesMedia
{
    public function playbackUrl(MediaManager $media, DateTimeInterface $exp): ?string
    {
        return $media->driver($this->media_driver)->playbackUrl($this->media_path, $exp);
    }
}
