<?php

namespace Database\Factories;

use App\Enums\ExchangeStatus;
use App\Enums\ExchangeType;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exchange>
 */
class ExchangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => MatchModel::factory(),
            'requester_id' => User::factory(),
            'requested_item_id' => Item::factory(),
            'offered_item_id' => null,
            'type' => ExchangeType::GIFT,
            'status' => ExchangeStatus::PENDING,
            'message' => $this->faker->sentence(),
        ];
    }
}
