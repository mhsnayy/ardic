<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'education_id' => $this->education_id,
            'personnel_id' => $this->personnel_id,
            'personnel_name' => $this->personnel_name,
            'personnel_email' => $this->personnel_email,
            'department_name' => $this->department_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'learning_link' => $this->activeLearningLink ? [
                'expires_at' => $this->activeLearningLink->expires_at->toIso8601String(),
                'created_at' => $this->activeLearningLink->created_at->toIso8601String(),
            ] : null, // never exposes the token
            'education' => $this->whenLoaded('education', fn () => [
                'id' => $this->education->id,
                'name' => $this->education->name,
                'type' => $this->education->type->value,
                'type_label' => $this->education->type->label(),
            ]),
        ];
    }
}
