<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentProgress extends Model
{
    protected $table = 'content_progress';

    protected $fillable = [
        'assignment_id', 'content_id', 'max_position_seconds', 'last_position_seconds',
        'last_heartbeat_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'max_position_seconds' => 'integer',
            'last_position_seconds' => 'integer',
            'last_heartbeat_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EducationAssignment::class, 'assignment_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(EducationContent::class, 'content_id');
    }
}
