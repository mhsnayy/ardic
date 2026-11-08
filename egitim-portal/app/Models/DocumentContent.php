<?php

namespace App\Models;

use App\Contracts\ContentType;
use App\Models\Concerns\CompletesOnOpen;
use App\Models\Concerns\DeletesMedia;
use App\Models\Concerns\ServesMedia;
use Illuminate\Database\Eloquent\Model;

class DocumentContent extends Model implements ContentType
{
    use CompletesOnOpen, DeletesMedia, ServesMedia; // opened = completed

    protected $fillable = ['media_driver', 'media_path', 'mime_type', 'size_bytes'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function kind(): string
    {
        return 'document';
    }

    public function toContentProperties(): array
    {
        return ['mime_type' => $this->mime_type, 'size_bytes' => $this->size_bytes];
    }
}
