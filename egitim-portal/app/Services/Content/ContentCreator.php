<?php

namespace App\Services\Content;

use App\Contracts\ContentType;

/** Creates the kind-specific (contentable) record and declares its validation rules. */
interface ContentCreator
{
    /** @return array<string, mixed> extra validation rules for this kind */
    public function rules(): array;

    /** @return array<string, string> */
    public function messages(): array;

    /** @param array<string, mixed> $data validated input */
    public function create(array $data): ContentType;
}
