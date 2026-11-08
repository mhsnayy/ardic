<?php

namespace App\Services\Content;

use App\Contracts\ContentType;
use App\Models\DocumentContent;

class DocumentContentCreator extends MediaContentCreator
{
    protected function kind(): string
    {
        return 'document';
    }

    protected function build(array $attributes, array $data): ContentType
    {
        return DocumentContent::create($attributes);
    }
}
