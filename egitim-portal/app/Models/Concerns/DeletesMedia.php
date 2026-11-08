<?php

namespace App\Models\Concerns;

use App\Media\MediaManager;

/** For contentables backed by a stored file: remove the file when the record is deleted. */
trait DeletesMedia
{
    protected static function bootDeletesMedia(): void
    {
        static::deleted(function ($model) {
            app(MediaManager::class)->driver($model->media_driver)->delete($model->media_path);
        });
    }
}
