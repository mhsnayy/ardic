<?php

namespace App\Models\Concerns;

use App\Models\ContentProgress;
use Carbon\CarbonInterface;

/** Document/link behaviour: reporting progress at all means "opened" / "clicked" (BR-6). */
trait CompletesOnOpen
{
    public function isCompletedBy(ContentProgress $p): bool
    {
        return $p->completed_at !== null;
    }

    public function progressRules(): array
    {
        return [];
    }

    public function recordProgress(ContentProgress $p, array $input, CarbonInterface $now): void
    {
        $p->completed_at ??= $now;
    }
}
