<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\EducationType;
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

    public function assignments(): HasMany
    {
        return $this->hasMany(EducationAssignment::class);
    }

    /** Adds per-status assignment counts without N+1. */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query->withCount([
            'assignments as total_count',
            'assignments as assigned_count' => fn ($q) => $q->where('status', AssignmentStatus::Assigned->value),
            'assignments as in_progress_count' => fn ($q) => $q->where('status', AssignmentStatus::InProgress->value),
            'assignments as completed_count' => fn ($q) => $q->where('status', AssignmentStatus::Completed->value),
        ]);
    }
}
