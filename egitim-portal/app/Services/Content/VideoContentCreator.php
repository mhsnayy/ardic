<?php

namespace App\Services\Content;

use App\Contracts\ContentType;
use App\Models\Education;
use App\Models\VideoContent;

class VideoContentCreator extends MediaContentCreator
{
    protected function kind(): string
    {
        return 'video';
    }

    public function rules(Education $education): array
    {
        return parent::rules($education) + [
            // Read by the admin's browser from the <video> metadata before upload (assumption 3).
            'duration_seconds' => ['required', 'numeric', 'min:1', 'max:86400'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'duration_seconds.required' => 'Video süresi okunamadı.',
            'duration_seconds.numeric' => 'Video süresi okunamadı.',
            'duration_seconds.min' => 'Video süresi okunamadı.',
            'duration_seconds.max' => 'Video süresi en fazla 24 saat olabilir.',
        ];
    }

    protected function build(array $attributes, array $data): ContentType
    {
        return VideoContent::create($attributes + ['duration_seconds' => (int) ceil((float) $data['duration_seconds'])]);
    }
}
