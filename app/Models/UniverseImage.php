<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['universe_id', 'path'])]
class UniverseImage extends Model
{
    use HasFactory;

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }
}
