<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expects the contentable relation to be loaded. */
class ContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'education_id' => $this->education_id,
            'kind' => $this->contentable->kind(),
            'title' => $this->title,
            'sort_order' => $this->sort_order,
            'is_required' => $this->is_required,
            'properties' => $this->contentable->toContentProperties(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
