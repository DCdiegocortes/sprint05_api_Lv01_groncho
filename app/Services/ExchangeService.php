<?php

namespace App\Services;

use App\Enums\ExchangeStatus;
use App\Models\Exchange;
use App\Models\User;

class ExchangeService
{
    public function exceedsGiveReceiveBalance(User $user): bool
    {
        $received = Exchange::where('requester_id', $user->id)
            ->where('status', ExchangeStatus::FINISHED)
            ->count();

        $given = Exchange::where('status', ExchangeStatus::FINISHED)
            ->where('requester_id', '!=', $user->id)
            ->whereHas('match', function ($query) use ($user) {
                $query->where('user_one_id', $user->id)
                    ->orWhere('user_two_id', $user->id);
            })
            ->count();

        return ($received - $given) > 2;
    }
}
