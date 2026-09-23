<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['swiper_user_id', 'target_user_id', 'liked'])]
class Swipe extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'liked' => 'boolean',
        ];
    }

    public function swiper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'swiper_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
