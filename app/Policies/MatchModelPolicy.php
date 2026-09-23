<?php

namespace App\Policies;

use App\Models\MatchModel;
use App\Models\User;

class MatchModelPolicy
{
    public function close(User $user, MatchModel $matchModel): bool
    {
        return $user->role === 'admin' || $matchModel->involves($user);
    }
}
