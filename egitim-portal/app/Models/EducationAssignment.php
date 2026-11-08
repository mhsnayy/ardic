<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EducationAssignment extends Model
{
    protected $table = 'education_assignments';

    protected $fillable = [
        'education_id', 'personnel_id', 'personnel_name', 'personnel_email',
        'department_name', 'status', 'assigned_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function education(): BelongsTo
    {
        return $this->belongsTo(Education::class);
    }

    public function learningLinks(): HasMany
    {
        return $this->hasMany(LearningLink::class, 'assignment_id');
    }

    /** The single currently valid link (BR-9), if any. */
    public function activeLearningLink(): HasOne
    {
        return $this->hasOne(LearningLink::class, 'assignment_id')->active()->latest('id');
    }

    /** The only way to change status (BR-5). */
    public function changeStatus(AssignmentStatus $status): self
    {
        $this->status = $status;
        $this->completed_at = $status === AssignmentStatus::Completed
            ? ($this->completed_at ?? now())
            : null;
        $this->save();

        return $this;
    }
}
