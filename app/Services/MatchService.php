<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\MatchModel;
use App\Models\Swipe;
use App\Models\User;

class MatchService
{
    public function checkForMutualLike(User $swiper, User $target): ?MatchModel
    {
        $targetAlreadyLikedSwiper = Swipe::where('swiper_user_id', $target->id)
            ->where('target_user_id', $swiper->id)
            ->where('liked', true)
            ->exists();

        if (! $targetAlreadyLikedSwiper) {
            return null;
        }

        $userOneId = min($swiper->id, $target->id);
        $userTwoId = max($swiper->id, $target->id);

        return MatchModel::create([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
            'status' => MatchStatus::ACTIVE,
        ]);
    }
}
