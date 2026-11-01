<?php

namespace Database\Seeders;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Database\Seeder;

class UniverseSeeder extends Seeder
{
    private const UNIVERSES = [
        'ana@groncho.com' => ['Soft Minimal', 'minimal, neutral'],
        'beto@groncho.com' => ['Street Vintage', 'streetwear, vintage'],
        'carla@groncho.com' => ['Boho Garden', 'boho, floral'],
        'dani@groncho.com' => ['Dark Academia', 'academia, tweed'],
        'eli@groncho.com' => ['Sport Chic', 'sporty, urban'],
    ];

    public function run(): void
    {
        foreach (self::UNIVERSES as $email => [$name, $style]) {
            $user = User::where('email', $email)->firstOrFail();

            Universe::firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $name, 'style' => $style]
            );
        }
    }
}
