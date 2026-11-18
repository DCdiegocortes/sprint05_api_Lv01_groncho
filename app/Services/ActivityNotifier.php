<?php

namespace App\Services;

use App\Enums\ExchangeStatus;
use App\Enums\NotificationType;
use App\Models\Exchange;
use App\Models\MatchModel;
use App\Models\User;
use App\Notifications\ActivityNotification;

class ActivityNotifier
{
    public function exchangeRequested(Exchange $exchange, User $actor): void
    {
        $exchange->requestedItem->user->notify($this->forExchange(NotificationType::EXCHANGE_REQUESTED, $exchange, $actor));
    }

    public function exchangeAnswered(Exchange $exchange, User $actor): void
    {
        $type = match ($exchange->status) {
            ExchangeStatus::ACCEPTED => NotificationType::EXCHANGE_ACCEPTED,
            ExchangeStatus::REJECTED => NotificationType::EXCHANGE_REJECTED,
            ExchangeStatus::FINISHED => NotificationType::EXCHANGE_FINISHED,
            default => null,
        };

        if ($type) {
            $exchange->requester->notify($this->forExchange($type, $exchange, $actor));
        }
    }

    public function exchangeCancelled(Exchange $exchange, User $actor): void
    {
        $exchange->requestedItem->user->notify($this->forExchange(NotificationType::EXCHANGE_CANCELLED, $exchange, $actor));
    }

    public function matchCreated(MatchModel $match): void
    {
        $match->userOne->notify(new ActivityNotification(NotificationType::MATCH_CREATED, [
            'actor_id' => $match->user_two_id,
            'match_id' => $match->id,
        ]));

        $match->userTwo->notify(new ActivityNotification(NotificationType::MATCH_CREATED, [
            'actor_id' => $match->user_one_id,
            'match_id' => $match->id,
        ]));
    }

    private function forExchange(NotificationType $type, Exchange $exchange, User $actor): ActivityNotification
    {
        return new ActivityNotification($type, [
            'actor_id' => $actor->id,
            'exchange_id' => $exchange->id,
            'item_name' => $exchange->requestedItem->name,
            'exchange_type' => $exchange->type->value,
        ]);
    }
}
