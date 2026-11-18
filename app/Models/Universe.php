<?php

namespace App\Models;

use App\Enums\UniverseLayout;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'style',
    'description',
    'layout',
    'spotify_track_id',
    'spotify_track_title',
    'spotify_track_cover_url',
    'decorations',
])]
class Universe extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'layout' => UniverseLayout::class,
            'decorations' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(UniverseImage::class)->orderBy('position');
    }
}
