<?php

namespace App\Policies;

use App\Models\Exchange;
use App\Models\User;

class ExchangePolicy
{
    public function view(User $user, Exchange $exchange): bool
    {
        return $user->role === 'admin'
            || $user->id === $exchange->requester_id
            || $user->id === $exchange->requestedItem->user_id;
    }
}
