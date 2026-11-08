<?php

namespace App\Contracts;

use App\Media\MediaManager;
use App\Models\ContentProgress;
use Carbon\CarbonInterface;
use DateTimeInterface;

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

    /** Validation rules for the learner's progress report body (BR-8 inputs for video, none otherwise). @return array<string, mixed> */
    public function progressRules(): array;

    /** Applies one progress report to the (unsaved) progress row. @param array<string, mixed> $input validated body */
    public function recordProgress(ContentProgress $p, array $input, CarbonInterface $now): void;

    /** Time-limited URL the learner's browser loads directly; null when the kind has none (link). */
    public function playbackUrl(MediaManager $media, DateTimeInterface $exp): ?string;
}
