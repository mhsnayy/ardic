<?php

namespace App\Models;

use App\Contracts\ContentType;
use App\Models\Concerns\DeletesMedia;
use Illuminate\Database\Eloquent\Model;

class VideoContent extends Model implements ContentType
{
    use DeletesMedia;

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

    public function toContentProperties(): array
    {
        return [
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
        ];
    }
}
