<?php

namespace Database\Seeders;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Database\Seeder;

class UniverseSeeder extends Seeder
{
    private const UNIVERSES = [
        'ana@groncho.com' => ['Soft Minimal', 'MINIMAL, NEUTRAL'],
        'beto@groncho.com' => ['Street Vintage', 'STREETWEAR, VINTAGE'],
        'carla@groncho.com' => ['Boho Garden', 'BOHO, FLORAL'],
        'dani@groncho.com' => ['Dark Academia', 'ACADEMIA, TWEED'],
        'eli@groncho.com' => ['Sport Chic', 'SPORTY, URBAN'],
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
