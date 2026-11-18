<?php

namespace App\Models;

use App\Enums\UniverseLayout;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'style', 'description', 'layout'])]
class Universe extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'layout' => UniverseLayout::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(UniverseImage::class);
    }
}
