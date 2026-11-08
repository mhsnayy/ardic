<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaUpload extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'education_id', 'kind', 'media_driver', 'path', 'mime_type', 'max_bytes',
        'size_bytes', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'max_bytes' => 'integer', 'size_bytes' => 'integer'];
    }
}
