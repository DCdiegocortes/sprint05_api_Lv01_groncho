<?php

namespace Tests\Feature\Profile;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileAvatarMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_18_191644_add_avatar_to_users_table.php');
    }

    public function test_existing_users_keep_their_data_and_have_no_avatar(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::table('users')->insert([
            'name' => 'Existing user',
            'email' => 'existing@example.com',
            'password' => 'hashed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'avatar' => null,
        ]);
    }
}
