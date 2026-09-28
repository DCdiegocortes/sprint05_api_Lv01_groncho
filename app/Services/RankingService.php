<?php

namespace App\Services;

use App\Enums\ExchangeStatus;
use App\Models\Exchange;
use App\Models\Item;
use Illuminate\Support\Collection;

class RankingService
{
    public function usersBySuccessRate(): Collection
    {
        return Exchange::selectRaw('requester_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as finished', [ExchangeStatus::FINISHED->value])
            ->groupBy('requester_id')
            ->with('requester')
            ->get()
            ->map(fn ($row) => [
                'user' => $row->requester,
                'success_rate' => round($row->finished / $row->total, 4),
            ])
            ->sortByDesc('success_rate')
            ->values();
    }

    public function itemsByRequestCount(): Collection
    {
        return Exchange::selectRaw('requested_item_id, count(*) as request_count')
            ->groupBy('requested_item_id')
            ->with('requestedItem')
            ->get()
            ->map(fn ($row) => [
                'item' => $row->requestedItem,
                'request_count' => $row->request_count,
            ])
            ->sortByDesc('request_count')
            ->values();
    }
}
