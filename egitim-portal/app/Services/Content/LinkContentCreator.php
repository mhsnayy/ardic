<?php

namespace App\Services\Content;

use App\Contracts\ContentType;
use App\Models\Education;
use App\Models\LinkContent;

class LinkContentCreator implements ContentCreator
{
    public function rules(Education $education): array
    {
        return ['url' => ['required', 'string', 'url:http,https', 'max:2000']];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'Bağlantı adresi zorunludur.',
            'url.url' => 'Geçerli bir http/https adresi girin.',
            'url.max' => 'Bağlantı adresi en fazla 2000 karakter olabilir.',
        ];
    }

    public function create(array $data, Education $education): ContentType
    {
        return LinkContent::create(['url' => $data['url']]);
    }
}
