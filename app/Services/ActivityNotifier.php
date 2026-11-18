<?php

namespace App\Services;

use App\Enums\ExchangeStatus;
use App\Enums\NotificationType;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\Universe;
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

    public function universePhotoRemoved(Universe $universe, User $actor, int $count = 1): void
    {
        if ($actor->id === $universe->user_id) {
            return;
        }

        $universe->user->notify(new ActivityNotification(NotificationType::UNIVERSE_PHOTO_REMOVED, [
            'universe_id' => $universe->id,
            'photos_removed' => $count,
        ]));
    }

    public function itemPhotoRemoved(Item $item, User $actor, int $count = 1): void
    {
        if ($actor->id === $item->user_id) {
            return;
        }

        $item->user->notify(new ActivityNotification(NotificationType::ITEM_PHOTO_REMOVED, [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'photos_removed' => $count,
        ]));
    }

    public function itemRemoved(Item $item, User $actor): void
    {
        if ($actor->id === $item->user_id) {
            return;
        }

        $item->user->notify(new ActivityNotification(NotificationType::ITEM_REMOVED, [
            'item_name' => $item->name,
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
