<?php

namespace Tests\Feature\Universes;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniverseStyleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_18_005448_add_description_and_widen_style_on_universes_table.php');
    }

    public function test_the_migration_converts_the_existing_styles_to_uppercase(): void
    {
        $migration = $this->migration();
        $migration->down();
        $user = User::factory()->create();
        DB::table('universes')->insert([
            'user_id' => $user->id,
            'name' => 'Existing universe',
            'style' => 'minimal, neutral',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('universes', ['user_id' => $user->id, 'style' => 'MINIMAL, NEUTRAL']);
    }

    public function test_the_migration_keeps_an_empty_style_untouched(): void
    {
        $migration = $this->migration();
        $migration->down();
        $user = User::factory()->create();
        DB::table('universes')->insert([
            'user_id' => $user->id,
            'name' => 'Existing universe',
            'style' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('universes', ['user_id' => $user->id, 'style' => null, 'description' => null]);
    }
}
