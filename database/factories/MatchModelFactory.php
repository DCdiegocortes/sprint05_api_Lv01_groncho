<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchModel>
 */
class MatchModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_one_id' => User::factory(),
            'user_two_id' => User::factory(),
            'status' => MatchStatus::ACTIVE,
        ];
    }
}
