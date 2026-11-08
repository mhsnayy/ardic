<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_name', 'last_name', 'email', 'department_id'])]
class Personnel extends Model
{
    use HasFactory;

    protected $table = 'personnel';

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
