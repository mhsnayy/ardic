<?php

namespace App\Models;

use App\Contracts\ContentType;
use App\Models\Concerns\DeletesMedia;
use App\Models\Concerns\ServesMedia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class VideoContent extends Model implements ContentType
{
    use DeletesMedia, ServesMedia;

    protected $fillable = ['media_driver', 'media_path', 'mime_type', 'size_bytes', 'duration_seconds'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'duration_seconds' => 'integer'];
    }

    public function kind(): string
    {
        return 'video';
    }

    /** At least 90% watched; unknown duration never completes. */
    public function isCompletedBy(ContentProgress $p): bool
    {
        if (! $this->duration_seconds) {
            return false;
        }

        return $p->max_position_seconds >= (int) ceil($this->duration_seconds * 0.9);
    }

    public function progressRules(): array
    {
        return [
            'position' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * BR-8: accept at most (elapsed * 2) + 5 seconds of forward progress since the last heartbeat.
     * Rewinding is free; max_position only ever grows.
     */
    public function recordProgress(ContentProgress $p, array $input, CarbonInterface $now): void
    {
        // The player reports the duration once (assumption 3); a stored duration is never overwritten.
        if ($this->duration_seconds === null) {
            $reported = (int) ceil((float) $input['duration']);
            if ($reported > 0 && $reported <= 86400) {
                $this->duration_seconds = $reported;
                $this->save();
            }
        }

        $elapsed = $p->last_heartbeat_at ? max(0, $now->getTimestamp() - $p->last_heartbeat_at->getTimestamp()) : 0;
        $cap = $p->max_position_seconds + $elapsed * 2 + 5;
        $accepted = min((float) $input['position'], (float) $cap);
        if ($this->duration_seconds !== null) {
            $accepted = min($accepted, (float) $this->duration_seconds);
        }
        $accepted = (int) floor($accepted);

        $p->last_position_seconds = $accepted;
        $p->max_position_seconds = max($p->max_position_seconds, $accepted);
    }

    public function toContentProperties(): array
    {
        return [
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
        ];
    }
}
