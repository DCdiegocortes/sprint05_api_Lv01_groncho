<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const DEMO_ENVIRONMENTS = ['local', 'testing'];

    public function run(): void
    {
        $this->call(AdminSeeder::class);

        if (app()->environment(self::DEMO_ENVIRONMENTS)) {
            $this->call([
                UserSeeder::class,
                UniverseSeeder::class,
                ItemSeeder::class,
                SwipeSeeder::class,
                MatchSeeder::class,
                ExchangeSeeder::class,
            ]);
        }
    }
}
