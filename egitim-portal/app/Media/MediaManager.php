<?php

namespace App\Media;

use App\Contracts\MediaSource;
use Illuminate\Support\Manager;

/**
 * Picks the MediaSource for a driver name. Every stored record carries its own
 * media_driver, so reads/deletes use driver($record->media_driver); new uploads use the default.
 *
 * Adding a driver (s3, youtube) later: write a class implementing MediaSource and add a
 * createS3Driver()/createYoutubeDriver() method here. No other code changes.
 */
class MediaManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('media.driver', 'local');
    }

    public function driver($driver = null): MediaSource
    {
        return parent::driver($driver);
    }

    protected function createLocalDriver(): MediaSource
    {
        return new LocalMediaSource;
    }
}
