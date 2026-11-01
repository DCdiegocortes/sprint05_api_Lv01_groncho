<?php

namespace Database\Seeders;

use App\Enums\ExchangeStatus;
use App\Enums\ExchangeType;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use App\Services\ExchangeService;
use Illuminate\Database\Seeder;

class ExchangeSeeder extends Seeder
{
    private const EXCHANGES = [
        [
            'requester' => 'ana@groncho.com',
            'owner' => 'beto@groncho.com',
            'requested' => 'Vintage denim jacket',
            'offered' => null,
            'type' => ExchangeType::GIFT,
            'status' => ExchangeStatus::PENDING,
            'message' => 'Hi! Is the jacket still available?',
        ],
        [
            'requester' => 'dani@groncho.com',
            'owner' => 'beto@groncho.com',
            'requested' => 'Vintage denim jacket',
            'offered' => null,
            'type' => ExchangeType::GIFT,
            'status' => ExchangeStatus::PENDING,
            'message' => 'I would love this jacket!',
        ],
        [
            'requester' => 'beto@groncho.com',
            'owner' => 'ana@groncho.com',
            'requested' => 'White linen shirt',
            'offered' => 'Striped polo',
            'type' => ExchangeType::TRADE,
            'status' => ExchangeStatus::ACCEPTED,
            'message' => 'Shirt for my polo?',
        ],
        [
            'requester' => 'carla@groncho.com',
            'owner' => 'ana@groncho.com',
            'requested' => 'Black ankle boots',
            'offered' => null,
            'type' => ExchangeType::GIFT,
            'status' => ExchangeStatus::REJECTED,
            'message' => null,
        ],
        [
            'requester' => 'ana@groncho.com',
            'owner' => 'carla@groncho.com',
            'requested' => 'Floral midi dress',
            'offered' => null,
            'type' => ExchangeType::GIFT,
            'status' => ExchangeStatus::FINISHED,
            'message' => 'Such a pretty dress',
        ],
        [
            'requester' => 'dani@groncho.com',
            'owner' => 'beto@groncho.com',
            'requested' => 'Corduroy pants',
            'offered' => 'Plaid skirt',
            'type' => ExchangeType::TRADE,
            'status' => ExchangeStatus::FINISHED,
            'message' => 'Pants for my skirt',
        ],
    ];

    public function __construct(private ExchangeService $exchangeService) {}

    public function run(): void
    {
        foreach (self::EXCHANGES as $data) {
            $requesterId = User::where('email', $data['requester'])->firstOrFail()->id;
            $ownerId = User::where('email', $data['owner'])->firstOrFail()->id;
            $match = MatchModel::where('user_one_id', min($requesterId, $ownerId))
                ->where('user_two_id', max($requesterId, $ownerId))
                ->firstOrFail();
            $requestedItem = Item::where('name', $data['requested'])->firstOrFail();
            $offeredItem = $data['offered'] ? Item::where('name', $data['offered'])->firstOrFail() : null;

            $exchange = Exchange::firstOrCreate(
                [
                    'match_id' => $match->id,
                    'requester_id' => $requesterId,
                    'requested_item_id' => $requestedItem->id,
                ],
                [
                    'offered_item_id' => $offeredItem?->id,
                    'type' => $data['type'],
                    'status' => $data['status'],
                    'message' => $data['message'],
                ]
            );

            if ($exchange->wasRecentlyCreated) {
                $this->applyStatusEffects($exchange);
            }
        }
    }

    private function applyStatusEffects(Exchange $exchange): void
    {
        if ($exchange->status === ExchangeStatus::ACCEPTED) {
            $this->exchangeService->lockItems($exchange);
        }

        if ($exchange->status === ExchangeStatus::FINISHED) {
            $this->exchangeService->transferOwnership($exchange);
            $this->exchangeService->releaseItems($exchange);
        }
    }
}
