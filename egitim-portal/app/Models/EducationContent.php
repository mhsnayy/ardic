<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EducationContent extends Model
{
    protected $fillable = ['education_id', 'title', 'sort_order', 'is_required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // The contentable row has no FK; remove it together with its content.
        static::deleted(fn (self $content) => $content->contentable()->first()?->delete());
    }

    public function education(): BelongsTo
    {
        return $this->belongsTo(Education::class);
    }

    public function contentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ContentProgress::class, 'content_id');
    }
}
