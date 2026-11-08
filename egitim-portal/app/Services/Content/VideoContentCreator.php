<?php

namespace App\Services\Content;

use App\Contracts\ContentType;
use App\Models\VideoContent;

class VideoContentCreator extends MediaContentCreator
{
    protected function kind(): string
    {
        return 'video';
    }

    protected function build(array $attributes): ContentType
    {
        // duration_seconds stays null until the player reports it.
        return VideoContent::create($attributes);
    }
}
