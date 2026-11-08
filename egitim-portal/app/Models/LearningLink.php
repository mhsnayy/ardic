<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningLink extends Model
{
    protected $fillable = ['assignment_id', 'token_hash', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EducationAssignment::class, 'assignment_id');
    }

    /** Not revoked and not expired. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
