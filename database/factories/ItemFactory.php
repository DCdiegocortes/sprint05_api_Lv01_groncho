<?php

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'size' => $this->faker->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            'status' => ItemStatus::AVAILABLE,
        ];
    }
}
