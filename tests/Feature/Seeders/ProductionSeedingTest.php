<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_admin_is_seeded_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'admin@groncho.com']);
        $this->assertDatabaseCount('universes', 0);
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('matches', 0);
        $this->assertDatabaseCount('exchanges', 0);
    }
}
