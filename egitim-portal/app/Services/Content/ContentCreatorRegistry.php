<?php

namespace App\Services\Content;

/**
 * Kind => creator map. Step 2 adds 'video' and 'document' here; nothing else changes.
 */
class ContentCreatorRegistry
{
    /** @var array<string, class-string<ContentCreator>> */
    private const CREATORS = [
        'link' => LinkContentCreator::class,
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
