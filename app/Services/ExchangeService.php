<?php

namespace App\Services;

use App\Enums\ExchangeStatus;
use App\Enums\ExchangeType;
use App\Enums\ItemStatus;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExchangeService
{
    public function lockItems(Exchange $exchange): Collection
    {
        $itemIds = $this->involvedItemIds($exchange);

        return DB::transaction(function () use ($exchange, $itemIds) {
            Item::whereIn('id', $itemIds)->update(['status' => ItemStatus::UNAVAILABLE->value]);

            $declined = Exchange::where('status', ExchangeStatus::PENDING->value)
                ->where('id', '!=', $exchange->id)
                ->where(function ($query) use ($itemIds) {
                    $query->whereIn('requested_item_id', $itemIds)
                        ->orWhereIn('offered_item_id', $itemIds);
                })
                ->get();

            Exchange::whereIn('id', $declined->pluck('id'))->update(['status' => ExchangeStatus::REJECTED->value]);

            return $declined->each(fn (Exchange $declinedExchange) => $declinedExchange->status = ExchangeStatus::REJECTED);
        });
    }

    public function releaseItems(Exchange $exchange): void
    {
        Item::whereIn('id', $this->involvedItemIds($exchange))
            ->update(['status' => ItemStatus::AVAILABLE->value]);
    }

    public function transferOwnership(Exchange $exchange): void
    {
        $originalOwnerId = $exchange->requestedItem->user_id;

        $exchange->requestedItem->update(['user_id' => $exchange->requester_id]);

        if ($exchange->type === ExchangeType::TRADE) {
            $exchange->offeredItem->update(['user_id' => $originalOwnerId]);
        }
    }

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

    private function involvedItemIds(Exchange $exchange): array
    {
        return array_filter([$exchange->requested_item_id, $exchange->offered_item_id]);
    }
}
