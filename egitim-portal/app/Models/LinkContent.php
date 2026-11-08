<?php

namespace App\Models;

use App\Contracts\ContentType;
use App\Media\MediaManager;
use App\Models\Concerns\CompletesOnOpen;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class LinkContent extends Model implements ContentType
{
    use CompletesOnOpen; // clicked = completed

    protected $fillable = ['url'];

    public function kind(): string
    {
        return 'link';
    }

    public function playbackUrl(MediaManager $media, DateTimeInterface $exp): ?string
    {
        return null; // the address is in properties.url
    }

    public function toContentProperties(): array
    {
        return ['url' => $this->url];
    }
}
