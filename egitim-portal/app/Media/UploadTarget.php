<?php

namespace App\Media;

final readonly class UploadTarget
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $url,
        public string $method,
        public array $headers,
    ) {}
}
