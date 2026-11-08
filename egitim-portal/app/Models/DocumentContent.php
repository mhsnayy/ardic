<?php

namespace App\Models;

use App\Contracts\ContentType;
use Illuminate\Database\Eloquent\Model;

class DocumentContent extends Model implements ContentType
{
    protected $fillable = ['media_driver', 'media_path', 'mime_type', 'size_bytes'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function kind(): string
    {
        return 'document';
    }

    /** Opened = a progress row with completed_at set. */
    public function isCompletedBy(ContentProgress $p): bool
    {
        return $p->completed_at !== null;
    }

    public function toContentProperties(): array
    {
        return ['mime_type' => $this->mime_type, 'size_bytes' => $this->size_bytes];
    }
}
