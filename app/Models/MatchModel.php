<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_one_id', 'user_two_id', 'status'])]
class MatchModel extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function involves(User $user): bool
    {
        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }
}
