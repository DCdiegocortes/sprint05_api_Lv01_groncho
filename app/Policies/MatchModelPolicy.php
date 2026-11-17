<?php

namespace App\Policies;

use App\Enums\MatchStatus;
use App\Models\MatchModel;
use App\Models\User;

class MatchModelPolicy
{
    public function viewItems(User $user, MatchModel $matchModel): bool
    {
        return $matchModel->status === MatchStatus::ACTIVE && $matchModel->involves($user);
    }

    public function close(User $user, MatchModel $matchModel): bool
    {
        return $user->role === 'admin' || $matchModel->involves($user);
    }
}
