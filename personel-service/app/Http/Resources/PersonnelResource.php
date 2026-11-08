<?php

namespace App\Http\Resources;

use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Personnel */
class PersonnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name.' '.$this->last_name,
            'email' => $this->email,
            'department' => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ],
        ];
    }
}
