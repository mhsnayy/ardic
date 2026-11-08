<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\EducationType;
use App\Media\MediaManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Education extends Model
{
    protected $table = 'educations';

    protected $fillable = ['name', 'type', 'description'];

    protected function casts(): array
    {
        return ['type' => EducationType::class];
    }

    protected static function booted(): void
    {
        // DB cascade removes content rows, so delete via models to also drop contentables.
        // Media files go with them (contentable deletion, plus unattached uploads).
        static::deleting(function (self $e) {
            $e->contents()->get()->each->delete();
            MediaUpload::where('education_id', $e->id)->get()->each(function (MediaUpload $u) {
                app(MediaManager::class)->driver($u->media_driver)->delete($u->path);
                $u->delete();
            });
        });
    }

    public function contents(): HasMany
    {
        return $this->hasMany(EducationContent::class)->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EducationAssignment::class);
    }

    /** Adds per-status assignment counts without N+1. */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query->withCount([
            'contents as contents_count',
            'assignments as total_count',
            'assignments as assigned_count' => fn ($q) => $q->where('status', AssignmentStatus::Assigned->value),
            'assignments as in_progress_count' => fn ($q) => $q->where('status', AssignmentStatus::InProgress->value),
            'assignments as completed_count' => fn ($q) => $q->where('status', AssignmentStatus::Completed->value),
        ]);
    }
}
