<?php

namespace App\Services\Content;

/**
 * Kind => creator map. A new kind is one more line here.
 */
class ContentCreatorRegistry
{
    /** @var array<string, class-string<ContentCreator>> */
    private const CREATORS = [
        'link' => LinkContentCreator::class,
        'video' => VideoContentCreator::class,
        'document' => DocumentContentCreator::class,
    ];

    public function has(string $kind): bool
    {
        return isset(self::CREATORS[$kind]);
    }

    public function for(string $kind): ContentCreator
    {
        return app(self::CREATORS[$kind]);
    }
}
