<?php

namespace Tests\Feature\Auth;

use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_an_admin_user(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@groncho.com',
            'role' => 'admin',
        ]);
    }

    public function test_admin_seeder_does_not_duplicate_on_second_run(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 1);
    }
}
