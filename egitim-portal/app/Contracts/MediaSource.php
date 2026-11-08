<?php

namespace App\Contracts;

use App\Media\UploadTarget;
use DateTimeInterface;

interface MediaSource
{
    public function createUploadTarget(string $path, string $mime, int $maxBytes, DateTimeInterface $exp): UploadTarget;

    public function playbackUrl(string $path, DateTimeInterface $exp): string;

    public function exists(string $path): bool;

    public function delete(string $path): void;
}
