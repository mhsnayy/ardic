<?php

namespace App\Contracts;

use App\Models\ContentProgress;

/**
 * Behaviour contract of every content kind (BR-6).
 *
 * To add a new kind without touching existing services: create a model that
 * implements this interface (plus toContentProperties()), add it to the morph
 * map in AppServiceProvider and register a ContentCreator in ContentCreatorRegistry.
 */
interface ContentType
{
    /** video | document | link */
    public function kind(): string;

    public function isCompletedBy(ContentProgress $p): bool;

    /** Kind-specific part of the API resource. @return array<string, mixed> */
    public function toContentProperties(): array;
}
