<?php

namespace App\Http\Resources;

use App\Support\Progress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expects the model to be loaded via Education::withStats(). */
class EducationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = (int) $this->total_count;
        $completed = (int) $this->completed_count;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'stats' => [
                'total' => $total,
                'assigned' => (int) $this->assigned_count,
                'in_progress' => (int) $this->in_progress_count,
                'completed' => $completed,
                'completion_rate' => Progress::rate($total, $completed),
            ],
        ];
    }
}
