<?php

namespace Tests\Feature\Universes;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniverseDecorationsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_18_122431_add_decorations_to_universes_table.php');
    }

    public function test_existing_universes_keep_their_data_and_have_no_decorations(): void
    {
        $migration = $this->migration();
        $migration->down();
        $user = User::factory()->create();
        DB::table('universes')->insert([
            'user_id' => $user->id,
            'name' => 'Existing universe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('universes', [
            'name' => 'Existing universe',
            'decorations' => null,
        ]);
    }
}
