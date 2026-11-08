<?php

namespace App\Models;

use App\Contracts\ContentType;
use Illuminate\Database\Eloquent\Model;

class LinkContent extends Model implements ContentType
{
    protected $fillable = ['url'];

    public function kind(): string
    {
        return 'link';
    }

    /** Clicked = a progress row with completed_at set. */
    public function isCompletedBy(ContentProgress $p): bool
    {
        return $p->completed_at !== null;
    }

    public function toContentProperties(): array
    {
        return ['url' => $this->url];
    }
}
