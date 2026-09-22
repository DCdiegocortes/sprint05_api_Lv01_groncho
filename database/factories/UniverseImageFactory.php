<?php

namespace Database\Factories;

use App\Models\Universe;
use App\Models\UniverseImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UniverseImage>
 */
class UniverseImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'universe_id' => Universe::factory(),
            'path' => 'universe-images/'.$this->faker->uuid().'.jpg',
        ];
    }
}
